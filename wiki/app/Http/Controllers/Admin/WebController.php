<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WebVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebRequest;
use App\Models\Web;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WebController extends Controller
{
    public function index(): View
    {
        $webs = Web::query()->withCount(['articles', 'permissions'])->orderBy('title')->get();

        return view('admin.webs.index', compact('webs'));
    }

    public function create(): View
    {
        return view('admin.webs.create', [
            'web' => new Web(['visibility' => WebVisibility::Private]),
            'visibilities' => WebVisibility::cases(),
        ]);
    }

    public function store(StoreWebRequest $request, AuditLogger $audit): RedirectResponse
    {
        $web = Web::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $audit->write('web.created', $web, web: $web);

        return redirect()->route('admin.webs.permissions.index', $web)
            ->with('status', 'Das Web wurde angelegt. Richten Sie jetzt die Rechte ein.');
    }

    public function edit(Web $web): View
    {
        return view('admin.webs.edit', [
            'web' => $web,
            'visibilities' => WebVisibility::cases(),
        ]);
    }

    public function update(StoreWebRequest $request, Web $web, AuditLogger $audit): RedirectResponse
    {
        abort_if($web->is_admin_web, 403);
        $web->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);
        $audit->write('web.updated', $web, web: $web);

        $route = $request->user()->is_admin ? route('admin.webs.index') : route('webs.show', $web);

        return redirect($route)->with('status', 'Das Web wurde gespeichert.');
    }
}
