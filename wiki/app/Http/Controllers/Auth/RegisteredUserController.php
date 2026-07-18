<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(SystemSettings $settings): View
    {
        if ($settings->registrationMode() === 'closed') {
            return view('auth.register-disabled');
        }

        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $mode = $settings->registrationMode();
        abort_if($mode === 'closed', 403);
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'approved_at' => $mode === 'open' ? now() : null,
        ]);

        DB::afterCommit(fn () => event(new Registered($user)));

        $audit->write('auth.registered', $user, user: $user);

        if ($mode === 'approval') {
            return redirect()->route('login')->with('status', 'Das Konto wurde angelegt und wartet auf Freigabe.');
        }

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
