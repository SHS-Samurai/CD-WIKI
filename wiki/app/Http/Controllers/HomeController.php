<?php

namespace App\Http\Controllers;

use App\Models\Web;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $user?->loadMissing('groups');

        $webs = Web::query()
            ->with('permissions')
            ->where('is_admin_web', false)
            ->orderBy('title')
            ->get()
            ->filter(fn (Web $web) => $web->hasRight($user, 'view'));

        return view('home', compact('webs'));
    }
}
