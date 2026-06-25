<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/' . config('app.locale')));

Route::prefix('{locale}')
    ->where(['locale' => 'en|ro'])
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', fn () => view('pages.home'))->name('home');
    });
