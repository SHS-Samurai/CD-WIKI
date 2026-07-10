from urllib.parse import urlsplit

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
    _validate_node(document, path="doc")


def extract_text(document: dict) -> str:
    validate_prosemirror_document(document)
    parts: list[str] = []
    _collect_text(document, parts)
    return " ".join(part for part in parts if part).strip()


def _validate_node(node: dict, path: str) -> None:
    if not isinstance(node, dict):
        raise ValidationError(f"Ungueltiger Knoten bei {path}.")

    node_type = node.get("type")
    if node_type not in ALLOWED_NODE_TYPES:
        raise ValidationError(f"Nicht erlaubter Knoten: {node_type}")

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

    for index, child in enumerate(content):
        _validate_node(child, path=f"{path}.content[{index}]")


def _validate_mark(mark: dict, path: str) -> None:
    if not isinstance(mark, dict):
        raise ValidationError(f"Ungueltige Mark bei {path}.")

    mark_type = mark.get("type")
    if mark_type not in ALLOWED_MARK_TYPES:
        raise ValidationError(f"Nicht erlaubte Mark: {mark_type}")

    if mark_type == "link":
        href = mark.get("attrs", {}).get("href", "")
        if not isinstance(href, str):
            raise ValidationError("Link-Ziel muss Text sein.")
        scheme = urlsplit(href).scheme.lower()
        if scheme not in ALLOWED_LINK_SCHEMES:
            raise ValidationError("Link-Ziel verwendet ein nicht erlaubtes Schema.")


def _collect_text(node: dict, parts: list[str]) -> None:
    if node.get("type") == "text":
        parts.append(node.get("text", ""))
    for child in node.get("content", []) or []:
        _collect_text(child, parts)
