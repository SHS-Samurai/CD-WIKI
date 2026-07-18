<?php

namespace App\Http\Controllers;

use App\Models\Web;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebController extends Controller
{
    public function show(Request $request, Web $web): View
    {
        abort_unless($web->hasRight($request->user(), 'view'), 403);

        $articles = $web->articles()->latest('updated_at')->paginate(30);

        return view('webs.show', compact('web', 'articles'));
    }
}
