<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

Route::get('/setup', function () {
    return view('setup');
});

Route::get('/preferences', function () {
    return view('preferences');
});
