from pathlib import Path

from bs4 import BeautifulSoup
from django.conf import settings
from docx import Document
from openpyxl import load_workbook
from pypdf import PdfReader


def extract_attachment_text(path: Path, filename: str, content_type: str = "") -> str:
    extension = Path(filename).suffix.lower()
    try:
        if extension == ".pdf" or content_type == "application/pdf":
            text = _extract_pdf(path)
        elif extension == ".docx":
            text = _extract_docx(path)
        elif extension == ".xlsx":
            text = _extract_xlsx(path)
        elif extension in {".txt", ".md"} or content_type.startswith("text/plain"):
            text = _extract_text_file(path)
        elif extension == ".html" or content_type in {"text/html", "application/xhtml+xml"}:
            text = _extract_html(path)
        else:
            text = ""
    except Exception:
        return ""
    return _limit_text(text)


def _extract_pdf(path: Path) -> str:
    reader = PdfReader(str(path))
    return "\n".join(page.extract_text() or "" for page in reader.pages)


def _extract_docx(path: Path) -> str:
    document = Document(str(path))
    return "\n".join(paragraph.text for paragraph in document.paragraphs)


def _extract_xlsx(path: Path) -> str:
    workbook = load_workbook(filename=str(path), read_only=True, data_only=True)
    parts: list[str] = []
    try:
        for sheet in workbook.worksheets:
            for row in sheet.iter_rows(values_only=True):
                parts.extend(str(value) for value in row if value is not None)
    finally:
        workbook.close()
    return "\n".join(parts)


def _extract_text_file(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="replace")


def _extract_html(path: Path) -> str:
    soup = BeautifulSoup(path.read_text(encoding="utf-8", errors="replace"), "lxml")
    for tag in soup(["script", "style"]):
        tag.decompose()
    return soup.get_text(" ", strip=True)


def _limit_text(text: str) -> str:
    limit = settings.WIKI_SEARCH_ATTACHMENT_TEXT_LIMIT
    if len(text) <= limit:
        return text
    return text[:limit]
