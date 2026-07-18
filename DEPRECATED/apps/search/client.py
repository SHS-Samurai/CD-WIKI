import meilisearch
from django.conf import settings


SEARCHABLE_ATTRIBUTES = [
    "web_title",
    "topic_title",
    "wiki_path",
    "content_text",
    "attachment_names",
    "attachment_text",
    "last_editor_username",
]

FILTERABLE_ATTRIBUTES = [
    "topic_id",
    "web_id",
    "web_slug",
    "is_deleted",
]

SORTABLE_ATTRIBUTES = [
    "last_edited_at",
]


def get_client():
    config = settings.MEILISEARCH
    return meilisearch.Client(config["URL"], config.get("MASTER_KEY") or None)


def get_index():
    return get_client().index(settings.WIKI_SEARCH_INDEX_NAME)


def configure_index(index=None) -> None:
    index = index or get_index()
    index.update_searchable_attributes(SEARCHABLE_ATTRIBUTES)
    index.update_filterable_attributes(FILTERABLE_ATTRIBUTES)
    index.update_sortable_attributes(SORTABLE_ATTRIBUTES)
