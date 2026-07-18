from dataclasses import dataclass
from datetime import timedelta
from math import ceil

from django.conf import settings
from django.db import transaction
from django.utils import timezone
from django.utils.crypto import salted_hmac

from apps.request_metadata import get_client_ip

from .models import RateLimitBucket, RateLimitScope


@dataclass(frozen=True)
class RateLimitPolicy:
    limit: int
    window_seconds: int
    block_seconds: int


@dataclass(frozen=True)
class RateLimitState:
    blocked: bool
    retry_after: int
    attempt_count: int


def request_rate_limit_identifier(request) -> str:
    return get_client_ip(request) or "unknown"


def rate_limit_status(scope: str, identifier: str) -> RateLimitState:
    policy = _policy(scope)
    bucket = RateLimitBucket.objects.filter(
        scope=scope,
        key_hash=_key_hash(scope, identifier),
    ).first()
    if bucket is None:
        return RateLimitState(False, 0, 0)

    now = timezone.now()
    if _window_expired(bucket, policy, now):
        return RateLimitState(False, 0, 0)
    return _state(bucket, now)


def record_rate_limit_hit(scope: str, identifier: str) -> RateLimitState:
    policy = _policy(scope)
    now = timezone.now()
    with transaction.atomic():
        bucket, _ = RateLimitBucket.objects.select_for_update().get_or_create(
            scope=scope,
            key_hash=_key_hash(scope, identifier),
            defaults={"window_started_at": now},
        )
        if _window_expired(bucket, policy, now):
            bucket.attempt_count = 0
            bucket.window_started_at = now
            bucket.blocked_until = None

        if bucket.blocked_until and bucket.blocked_until > now:
            return _state(bucket, now)

        bucket.attempt_count += 1
        if bucket.attempt_count >= policy.limit:
            bucket.blocked_until = now + timedelta(seconds=policy.block_seconds)
        bucket.save(
            update_fields=[
                "attempt_count",
                "window_started_at",
                "blocked_until",
                "updated_at",
            ]
        )
        return _state(bucket, now)


def clear_rate_limit(scope: str, identifier: str) -> None:
    RateLimitBucket.objects.filter(
        scope=scope,
        key_hash=_key_hash(scope, identifier),
    ).delete()


def _policy(scope: str) -> RateLimitPolicy:
    if scope == RateLimitScope.LOGIN:
        return RateLimitPolicy(
            limit=max(1, settings.WIKI_LOGIN_RATE_LIMIT),
            window_seconds=max(1, settings.WIKI_LOGIN_RATE_WINDOW_SECONDS),
            block_seconds=max(1, settings.WIKI_LOGIN_RATE_BLOCK_SECONDS),
        )
    if scope == RateLimitScope.REGISTRATION:
        return RateLimitPolicy(
            limit=max(1, settings.WIKI_REGISTRATION_RATE_LIMIT),
            window_seconds=max(1, settings.WIKI_REGISTRATION_RATE_WINDOW_SECONDS),
            block_seconds=max(1, settings.WIKI_REGISTRATION_RATE_BLOCK_SECONDS),
        )
    raise ValueError(f"Unbekannter Rate-Limit-Scope: {scope}")


def _key_hash(scope: str, identifier: str) -> str:
    return salted_hmac(
        f"wiki.rate-limit.{scope}",
        identifier,
        algorithm="sha256",
    ).hexdigest()


def _window_expired(bucket, policy: RateLimitPolicy, now) -> bool:
    window_ends = bucket.window_started_at + timedelta(seconds=policy.window_seconds)
    block_expired = bucket.blocked_until is None or bucket.blocked_until <= now
    return window_ends <= now and block_expired


def _state(bucket, now) -> RateLimitState:
    if not bucket.blocked_until or bucket.blocked_until <= now:
        return RateLimitState(False, 0, bucket.attempt_count)
    seconds = ceil((bucket.blocked_until - now).total_seconds())
    return RateLimitState(True, max(1, seconds), bucket.attempt_count)
