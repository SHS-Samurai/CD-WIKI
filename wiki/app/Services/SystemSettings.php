<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSettings
{
    public function registrationMode(): string
    {
        if (! Schema::hasTable('system_settings')) {
            return 'closed';
        }

        return Cache::remember('wiki.system-settings.registration-mode', 3600, fn () => SystemSetting::query()->value('registration_mode') ?? 'closed');
    }

    public function clear(): void
    {
        Cache::forget('wiki.system-settings.registration-mode');
    }
}
