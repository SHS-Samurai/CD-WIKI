<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->withCount('articles')->orderBy('name')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.create', ['category' => new Category]);
    }

    public function store(StoreCategoryRequest $request, AuditLogger $audit): RedirectResponse
    {
        $category = Category::query()->create($request->validated());
        $audit->write('category.created', $category);

        return redirect()->route('admin.categories.index')->with('status', 'Die Kategorie wurde angelegt.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(StoreCategoryRequest $request, Category $category, AuditLogger $audit): RedirectResponse
    {
        $category->update($request->validated());
        $audit->write('category.updated', $category);

        return redirect()->route('admin.categories.index')->with('status', 'Die Kategorie wurde gespeichert.');
    }

    public function destroy(Category $category, AuditLogger $audit): RedirectResponse
    {
        $category->delete();
        $audit->write('category.deleted', $category);

        return redirect()->route('admin.categories.index')->with('status', 'Die Kategorie wurde gelöscht.');
    }
}
