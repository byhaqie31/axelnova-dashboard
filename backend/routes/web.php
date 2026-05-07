<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['service' => 'Axel Nova Platform API', 'status' => 'ok']);
});

Route::get('/up', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()->toISOString()]);
});
