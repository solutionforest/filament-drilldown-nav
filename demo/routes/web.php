<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Demo/CI only: `DEMO_AUTOLOGIN=true` lets the Octane smoke test sign in without driving the
// (Livewire) login form. Never enabled unless the variable is set, and never outside local/testing.
if (env('DEMO_AUTOLOGIN') === true && app()->environment(['local', 'testing'])) {
    Route::get('/_demo/login/{email}', function (string $email) {
        Auth::login(User::where('email', $email)->firstOrFail());

        return redirect('/admin');
    });
}
