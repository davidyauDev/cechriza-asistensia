<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (app()->environment('production')) {
        return response()->json(['message' => 'Not Found'], 404);
    }

    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
    ]);
});

Route::get('/login', function () {
    return response()->json(['message' => 'Please use /api/login for authentication'], 200);
})->name('login');
