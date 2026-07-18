from django.core.management.base import BaseCommand

from apps.search.client import configure_index
from apps.search.services import index_topic
from apps.topics.models import Topic


class Command(BaseCommand):
    help = "Baut den Meilisearch-Index fuer alle aktiven Topics neu auf."

    def handle(self, *args, **options):
        configure_index()
        count = 0
        topics = Topic.objects.select_related("web", "last_edited_by").filter(is_deleted=False)
        for topic in topics.iterator():
            index_topic(topic)
            count += 1
        self.stdout.write(self.style.SUCCESS(f"{count} Topics indexiert."))
