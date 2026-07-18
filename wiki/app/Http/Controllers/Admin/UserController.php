<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\Group;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', ['users' => User::query()->with('groups')->orderBy('name')->paginate(100)]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['user' => new User, 'groups' => Group::query()->orderBy('name')->get()]);
    }

    public function store(StoreUserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->safe()->except(['group_ids', 'is_approved']);
        $data['approved_at'] = $request->boolean('is_approved') ? now() : null;
        $user = User::query()->create($data);
        $user->groups()->sync($request->validated('group_ids', []));
        $audit->write('user.created', $user, ['is_admin' => $user->is_admin]);

        return redirect()->route('admin.users.index')->with('status', 'Der Benutzer wurde angelegt.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user->load('groups'), 'groups' => Group::query()->orderBy('name')->get()]);
    }

    public function update(StoreUserRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->safe()->except(['group_ids', 'password', 'is_approved']);
        $data['approved_at'] = $request->boolean('is_approved') ? ($user->approved_at ?? now()) : null;
        $adminIds = User::query()->where('is_admin', true)->lockForUpdate()->pluck('id');
        if ($request->boolean('is_admin') === false && $user->is_admin && $adminIds->count() === 1) {
            throw ValidationException::withMessages(['is_admin' => 'Der letzte Administrator kann nicht herabgestuft werden.']);
        }
        $approvedAdminIds = User::query()->where('is_admin', true)->whereNotNull('approved_at')->lockForUpdate()->pluck('id');
        if (! $request->boolean('is_approved') && $user->is_admin && $user->approved_at !== null && $approvedAdminIds->count() === 1) {
            throw ValidationException::withMessages(['is_approved' => 'Der letzte freigegebene Administrator kann nicht gesperrt werden.']);
        }
        if (filled($request->validated('password'))) {
            $data['password'] = $request->validated('password');
        }
        $user->update($data);
        $user->groups()->sync($request->validated('group_ids', []));
        $audit->write('user.updated', $user, ['is_admin' => $user->is_admin]);

        return redirect()->route('admin.users.index')->with('status', 'Der Benutzer wurde gespeichert.');
    }
}
