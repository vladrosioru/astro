<?php

use App\Http\Controllers\Admin\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AuthController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.attempt');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

Route::get('/', fn () => redirect('/' . config('app.locale')));

Route::prefix('{locale}')
    ->where(['locale' => 'en|ro'])
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\PageController::class, 'home'])->name('home');
        Route::get('/about', [\App\Http\Controllers\PageController::class, 'about'])->name('about');
        Route::get('/contact', [\App\Http\Controllers\PageController::class, 'contact'])->name('contact');
    });
