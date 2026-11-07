<?php

namespace App\Http\Controllers;

use App\Models\LoginSecurityEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginSecurityEventController extends Controller
{
    public function index(Request $request): View
    {
        $events = LoginSecurityEvent::query()
            ->with('user:id,name,email')
            ->latest('id')
            ->paginate(50);

        return view('security.login-events', ['events' => $events]);
    }
}
