<?php

use App\Http\Controllers\ImageUploadController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['digest_auth']], function () {
    Route::get('/', function () {
        return view('index');
    });
});

Route::post('/upload', [ImageUploadController::class, 'upload'])->name('image.upload');
