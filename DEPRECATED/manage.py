#!/usr/bin/env python
"""Django command-line utility for this project."""

import os
import sys


def main() -> None:
    os.environ.setdefault("DJANGO_SETTINGS_MODULE", "config.settings")

    try:
        from django.core.management import execute_from_command_line
    except ImportError as exc:
        raise ImportError(
            "Django is not installed. Activate the virtual environment and "
            "install requirements.txt."
        ) from exc

    execute_from_command_line(sys.argv)


if __name__ == "__main__":
    main()

