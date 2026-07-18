from django.contrib import messages
from django.shortcuts import render

from .services import SearchUnavailable, search_topics


def search_view(request):
    query = request.GET.get("q", "").strip()
    results = []
    if query:
        try:
            results = search_topics(query, request.user)
        except SearchUnavailable:
            messages.error(request, "Die Suche ist gerade nicht erreichbar.")

    return render(
        request,
        "search/results.html",
        {
            "query": query,
            "results": results,
        },
    )
