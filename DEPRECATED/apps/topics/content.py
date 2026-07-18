from urllib.parse import urlsplit

from django.conf import settings
from django.core.exceptions import ValidationError


ALLOWED_NODE_TYPES = {
    "doc",
    "paragraph",
    "text",
    "heading",
    "bulletList",
    "orderedList",
    "listItem",
    "blockquote",
    "codeBlock",
    "horizontalRule",
    "hardBreak",
    "table",
    "tableRow",
    "tableCell",
    "tableHeader",
}

ALLOWED_MARK_TYPES = {
    "bold",
    "italic",
    "link",
    "code",
}

ALLOWED_LINK_SCHEMES = {"", "http", "https", "mailto"}


def validate_prosemirror_document(document: dict) -> None:
    if not isinstance(document, dict):
        raise ValidationError("Topic-Inhalt muss ein JSON-Objekt sein.")
    if document.get("type") != "doc":
        raise ValidationError("Topic-Inhalt muss ein ProseMirror-Dokument sein.")
    state = {"nodes": 0, "text_length": 0}
    _validate_node(document, path="doc", depth=0, state=state)


def extract_text(document: dict) -> str:
    validate_prosemirror_document(document)
    parts: list[str] = []
    _collect_text(document, parts)
    return " ".join(part for part in parts if part).strip()


def _validate_node(node: dict, path: str, *, depth: int, state: dict) -> None:
    if not isinstance(node, dict):
        raise ValidationError(f"Ungueltiger Knoten bei {path}.")

    if depth > settings.WIKI_TOPIC_MAX_DEPTH:
        raise ValidationError("Topic-Inhalt ist zu tief verschachtelt.")
    state["nodes"] += 1
    if state["nodes"] > settings.WIKI_TOPIC_MAX_NODES:
        raise ValidationError("Topic-Inhalt enthaelt zu viele Knoten.")

    node_type = node.get("type")
    if node_type not in ALLOWED_NODE_TYPES:
        raise ValidationError(f"Nicht erlaubter Knoten: {node_type}")

    attrs = node.get("attrs", {})
    if attrs is None:
        attrs = {}
    if not isinstance(attrs, dict):
        raise ValidationError(f"Ungueltige Attribute bei {path}.")

    marks = node.get("marks", [])
    if marks is None:
        marks = []
    if not isinstance(marks, list):
        raise ValidationError(f"Ungueltige Marks bei {path}.")
    for mark in marks:
        _validate_mark(mark, path)

    content = node.get("content", [])
    if content is None:
        content = []
    if not isinstance(content, list):
        raise ValidationError(f"Ungueltiger Inhalt bei {path}.")

    if node_type == "text" and not isinstance(node.get("text", ""), str):
        raise ValidationError(f"Textknoten bei {path} enthaelt keinen Text.")
    if node_type == "text":
        state["text_length"] += len(node.get("text", ""))
        if state["text_length"] > settings.WIKI_TOPIC_MAX_TEXT_LENGTH:
            raise ValidationError("Topic-Inhalt enthaelt zu viel Text.")

    for index, child in enumerate(content):
        _validate_node(
            child,
            path=f"{path}.content[{index}]",
            depth=depth + 1,
            state=state,
        )


def _validate_mark(mark: dict, path: str) -> None:
    if not isinstance(mark, dict):
        raise ValidationError(f"Ungueltige Mark bei {path}.")

    mark_type = mark.get("type")
    if mark_type not in ALLOWED_MARK_TYPES:
        raise ValidationError(f"Nicht erlaubte Mark: {mark_type}")

    attrs = mark.get("attrs", {})
    if attrs is None:
        attrs = {}
    if not isinstance(attrs, dict):
        raise ValidationError(f"Ungueltige Mark-Attribute bei {path}.")

    if mark_type == "link":
        href = attrs.get("href", "")
        if not isinstance(href, str):
            raise ValidationError("Link-Ziel muss Text sein.")
        if len(href) > 2048:
            raise ValidationError("Link-Ziel ist zu lang.")
        scheme = urlsplit(href).scheme.lower()
        if scheme not in ALLOWED_LINK_SCHEMES:
            raise ValidationError("Link-Ziel verwendet ein nicht erlaubtes Schema.")


def _collect_text(node: dict, parts: list[str]) -> None:
    if node.get("type") == "text":
        parts.append(node.get("text", ""))
    for child in node.get("content", []) or []:
        _collect_text(child, parts)
