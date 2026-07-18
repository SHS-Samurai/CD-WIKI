<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateThemeRequest;
use App\Models\ThemeSetting;
use App\Services\AuditLogger;
use App\Services\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function edit(ThemeService $service): View
    {
        return view('admin.theme.edit', ['theme' => $service->current()]);
    }

    public function update(UpdateThemeRequest $request, ThemeService $service, AuditLogger $audit): RedirectResponse
    {
        $theme = ThemeSetting::query()->firstOrFail();
        $theme->update($request->validated());
        $service->clear();
        $audit->write('theme.updated', $theme);

        return back()->with('status', 'Layout und Theme wurden gespeichert.');
    }
}
