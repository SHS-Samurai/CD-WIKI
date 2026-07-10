from django.utils.html import conditional_escape, format_html
from django.utils.safestring import mark_safe

from .content import validate_prosemirror_document


def render_document(document: dict):
    validate_prosemirror_document(document)
    return _render_children(document)


def _render_children(node: dict):
    return mark_safe("".join(str(_render_node(child)) for child in node.get("content", []) or []))


def _render_node(node: dict):
    node_type = node.get("type")

    if node_type == "text":
        return _render_text(node)
    if node_type == "paragraph":
        return format_html("<p>{}</p>", _render_children(node))
    if node_type == "heading":
        level = _heading_level(node)
        return format_html("<h{}>{}</h{}>", level, _render_children(node), level)
    if node_type == "bulletList":
        return format_html("<ul>{}</ul>", _render_children(node))
    if node_type == "orderedList":
        return format_html("<ol>{}</ol>", _render_children(node))
    if node_type == "listItem":
        return format_html("<li>{}</li>", _render_children(node))
    if node_type == "blockquote":
        return format_html("<blockquote>{}</blockquote>", _render_children(node))
    if node_type == "codeBlock":
        return format_html("<pre><code>{}</code></pre>", _plain_text(node))
    if node_type == "horizontalRule":
        return mark_safe("<hr>")
    if node_type == "hardBreak":
        return mark_safe("<br>")
    if node_type == "table":
        return format_html(
            '<div class="table-scroll" role="region" aria-label="Tabelle" tabindex="0">'
            "<table><tbody>{}</tbody></table></div>",
            _render_children(node),
        )
    if node_type == "tableRow":
        return format_html("<tr>{}</tr>", _render_children(node))
    if node_type == "tableCell":
        return format_html("<td>{}</td>", _render_children(node))
    if node_type == "tableHeader":
        return format_html("<th>{}</th>", _render_children(node))
    return mark_safe("")


def _render_text(node: dict):
    rendered = conditional_escape(node.get("text", ""))
    for mark in node.get("marks", []) or []:
        mark_type = mark.get("type")
        if mark_type == "bold":
            rendered = format_html("<strong>{}</strong>", rendered)
        elif mark_type == "italic":
            rendered = format_html("<em>{}</em>", rendered)
        elif mark_type == "code":
            rendered = format_html("<code>{}</code>", rendered)
        elif mark_type == "link":
            href = mark.get("attrs", {}).get("href", "")
            rendered = format_html('<a href="{}" rel="nofollow">{}</a>', href, rendered)
    return rendered


def _heading_level(node: dict) -> int:
    level = node.get("attrs", {}).get("level", 2)
    if not isinstance(level, int):
        return 2
    return min(max(level, 1), 6)


def _plain_text(node: dict) -> str:
    parts: list[str] = []
    _collect_text(node, parts)
    return "".join(parts)


def _collect_text(node: dict, parts: list[str]) -> None:
    if node.get("type") == "text":
        parts.append(node.get("text", ""))
    for child in node.get("content", []) or []:
        _collect_text(child, parts)
