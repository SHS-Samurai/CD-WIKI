<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Web;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $visibleWebIds = $this->visibleWebIds($request);
        $categories = Category::query()
            ->withCount(['articles' => fn ($query) => $query->whereIn('web_id', $visibleWebIds)])
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function show(Request $request, Category $category): View
    {
        $visibleWebIds = $this->visibleWebIds($request);
        $articles = $category->articles()
            ->with('web')
            ->whereIn('web_id', $visibleWebIds)
            ->latest('updated_at')
            ->paginate(40);

        return view('categories.show', compact('category', 'articles'));
    }

    private function visibleWebIds(Request $request): array
    {
        $user = $request->user();
        $user?->loadMissing('groups');

        return Web::query()->with('permissions')->get()
            ->filter(fn (Web $web) => $web->hasRight($user, 'view'))
            ->modelKeys();
    }
}
