from ipaddress import ip_address, ip_network

from django.conf import settings


def get_client_ip(request) -> str | None:
    remote_ip = _valid_ip(request.META.get("REMOTE_ADDR", ""))
    if remote_ip is None:
        return None
    if not _is_trusted_proxy(remote_ip):
        return remote_ip

    forwarded_ips = [
        parsed
        for value in request.META.get("HTTP_X_FORWARDED_FOR", "").split(",")
        if (parsed := _valid_ip(value.strip())) is not None
    ]
    for candidate in reversed(forwarded_ips):
        if not _is_trusted_proxy(candidate):
            return candidate
    return forwarded_ips[0] if forwarded_ips else remote_ip


def _valid_ip(value: str) -> str | None:
    try:
        return str(ip_address(value))
    except ValueError:
        return None


def _is_trusted_proxy(value: str) -> bool:
    address = ip_address(value)
    for entry in settings.WIKI_TRUSTED_PROXY_IPS:
        try:
            if address in ip_network(entry, strict=False):
                return True
        except ValueError:
            continue
    return False
