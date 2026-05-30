<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\Admin\HeroSectionController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/files/{path}', function ($path) {
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return response()->file(storage_path('app/public/' . $path));
})->where('path', '.*');
