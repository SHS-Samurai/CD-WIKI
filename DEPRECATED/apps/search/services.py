from __future__ import annotations

import logging
from dataclasses import dataclass

from django.conf import settings

from apps.topics.models import Topic
from apps.webs.permissions import user_can_view

from .client import get_index
from .documents import build_topic_document


logger = logging.getLogger(__name__)


class SearchUnavailable(Exception):
    pass


@dataclass(frozen=True)
class SearchResult:
    topic: Topic
    hit: dict


def index_topic(topic: Topic):
    document = build_topic_document(topic)
    return get_index().add_documents([document], primary_key="id")


def index_topic_safely(topic: Topic) -> bool:
    try:
        index_topic(topic)
    except Exception as exc:
        logger.info("Suchindex konnte nicht aktualisiert werden: %s", exc)
        return False
    return True


def search_topics(query: str, user, *, limit: int | None = None) -> list[SearchResult]:
    query = query.strip()
    if not query:
        return []

    limit = limit or settings.WIKI_SEARCH_RESULT_LIMIT
    raw_hits = _search_raw(query)
    topic_ids = _topic_ids(raw_hits)
    topics_by_id = _visible_topics_by_id(topic_ids, user)

    results: list[SearchResult] = []
    for hit in raw_hits:
        topic = topics_by_id.get(hit.get("topic_id"))
        if topic:
            results.append(SearchResult(topic=topic, hit=hit))
        if len(results) >= limit:
            break
    return results


def _search_raw(query: str) -> list[dict]:
    try:
        response = get_index().search(
            query,
            {
                "limit": settings.WIKI_SEARCH_BACKEND_LIMIT,
                "attributesToRetrieve": [
                    "topic_id",
                    "web_slug",
                    "web_title",
                    "topic_slug",
                    "topic_title",
                    "wiki_path",
                    "content_text",
                    "attachment_names",
                    "attachment_text",
                    "last_edited_at",
                    "last_editor_username",
                    "current_revision",
                    "change_note",
                    "is_deleted",
                ],
            },
        )
    except Exception as exc:
        raise SearchUnavailable("Suchdienst ist nicht erreichbar.") from exc
    return [hit for hit in response.get("hits", []) if not hit.get("is_deleted")]


def _topic_ids(hits: list[dict]) -> list[int]:
    ids: list[int] = []
    for hit in hits:
        try:
            topic_id = int(hit["topic_id"])
        except (KeyError, TypeError, ValueError):
            continue
        if topic_id not in ids:
            ids.append(topic_id)
    return ids


def _visible_topics_by_id(topic_ids: list[int], user) -> dict[int, Topic]:
    if not topic_ids:
        return {}
    topics = Topic.objects.select_related("web", "last_edited_by").filter(
        id__in=topic_ids,
        is_deleted=False,
    )
    return {
        topic.pk: topic
        for topic in topics
        if user_can_view(user, topic.web)
    }
