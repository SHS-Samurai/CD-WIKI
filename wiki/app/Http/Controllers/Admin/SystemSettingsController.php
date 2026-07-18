<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function edit(SystemSettings $settings): View
    {
        return view('admin.settings.edit', ['registrationMode' => $settings->registrationMode()]);
    }

    public function update(UpdateSystemSettingsRequest $request, SystemSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $model = SystemSetting::query()->firstOrFail();
        $model->update($request->validated());
        $settings->clear();
        $audit->write('settings.registration_updated', $model, $request->validated());

        return back()->with('status', 'Die Registrierungseinstellung wurde gespeichert.');
    }
}
