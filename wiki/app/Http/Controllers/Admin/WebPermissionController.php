<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WebPermissionSubject;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebPermissionRequest;
use App\Models\Group;
use App\Models\User;
use App\Models\Web;
use App\Models\WebPermission;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WebPermissionController extends Controller
{
    public function index(Web $web): View
    {
        return view('admin.webs.permissions', [
            'web' => $web,
            'permissions' => $web->permissions()->with(['user', 'group'])->get(),
            'subjectTypes' => WebPermissionSubject::cases(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreWebPermissionRequest $request, Web $web, AuditLogger $audit): RedirectResponse
    {
        abort_if($web->is_admin_web, 403);
        $data = $request->validated();
        $web->permissions()->updateOrCreate(
            $this->subjectLookup($data),
            $data,
        );
        $audit->write('web.permissions_updated', $web, ['subject_type' => $data['subject_type']], web: $web);

        return back()->with('status', 'Die Web-Rechte wurden gespeichert.');
    }

    public function destroy(Web $web, WebPermission $permission, AuditLogger $audit): RedirectResponse
    {
        abort_unless($permission->web_id === $web->id && ! $web->is_admin_web, 404);
        $permission->delete();
        $audit->write('web.permission_removed', $web, ['subject_key' => $permission->subject_key], web: $web);

        return back()->with('status', 'Der Rechteintrag wurde entfernt.');
    }

    private function subjectLookup(array $data): array
    {
        return match ($data['subject_type']) {
            WebPermissionSubject::User->value => ['subject_key' => 'user:'.$data['user_id']],
            WebPermissionSubject::Group->value => ['subject_key' => 'group:'.$data['group_id']],
            WebPermissionSubject::Authenticated->value => ['subject_key' => WebPermissionSubject::Authenticated->value],
            WebPermissionSubject::Public->value => ['subject_key' => WebPermissionSubject::Public->value],
        };
    }
}
