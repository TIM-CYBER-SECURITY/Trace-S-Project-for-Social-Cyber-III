<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/** Pintu masuk pasca-login: arahkan ke dashboard sesuai peran. */
class HomeController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->hasPermission('system.health')) {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('operator.dashboard');
    }
}
