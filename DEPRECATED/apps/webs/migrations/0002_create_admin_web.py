from django.db import migrations


def create_admin_web(apps, schema_editor):
    Web = apps.get_model("webs", "Web")
    Web.objects.update_or_create(
        slug="admin",
        defaults={
            "title": "Admin",
            "description": "Zentrale Verwaltung",
            "visibility": "private",
            "is_admin_web": True,
        },
    )


def remove_admin_web(apps, schema_editor):
    Web = apps.get_model("webs", "Web")
    Web.objects.filter(slug="admin", is_admin_web=True).delete()


class Migration(migrations.Migration):

    dependencies = [
        ("webs", "0001_initial"),
    ]

    operations = [
        migrations.RunPython(create_admin_web, remove_admin_web),
    ]
