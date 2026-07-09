<?php

use Illuminate\Support\Facades\Route;

Route::get('/reset-password/{token}', function (string $token) {
    $url = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/').'/reset-password?'.http_build_query([
        'token' => $token,
        'email' => request('email'),
    ]);

    return redirect()->away($url);
})->name('password.reset');

Route::get('/', function () {
    return view('welcome');
});
