from datetime import timedelta

from django.conf import settings
from django.core.management.base import BaseCommand
from django.utils import timezone

from apps.accounts.models import RateLimitBucket


class Command(BaseCommand):
    help = "Entfernt abgelaufene Rate-Limit-Zaehlstaende."

    def handle(self, *args, **options):
        retention_hours = max(1, settings.WIKI_RATE_LIMIT_RETENTION_HOURS)
        threshold = timezone.now() - timedelta(hours=retention_hours)
        deleted, _ = RateLimitBucket.objects.filter(updated_at__lt=threshold).delete()
        self.stdout.write(self.style.SUCCESS(f"{deleted} Rate-Limit-Eintraege entfernt."))
