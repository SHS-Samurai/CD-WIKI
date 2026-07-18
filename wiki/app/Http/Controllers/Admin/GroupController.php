<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupRequest;
use App\Models\Group;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(): View
    {
        return view('admin.groups.index', ['groups' => Group::query()->withCount('users')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.groups.create', ['group' => new Group]);
    }

    public function store(StoreGroupRequest $request, AuditLogger $audit): RedirectResponse
    {
        $group = Group::query()->create($request->validated());
        $audit->write('group.created', $group);

        return redirect()->route('admin.groups.index')->with('status', 'Die Gruppe wurde angelegt.');
    }

    public function edit(Group $group): View
    {
        return view('admin.groups.edit', compact('group'));
    }

    public function update(StoreGroupRequest $request, Group $group, AuditLogger $audit): RedirectResponse
    {
        $group->update($request->validated());
        $audit->write('group.updated', $group);

        return redirect()->route('admin.groups.index')->with('status', 'Die Gruppe wurde gespeichert.');
    }
}
