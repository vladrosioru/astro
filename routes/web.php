<?php

use App\Http\Controllers\Admin\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AuthController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.attempt');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

Route::prefix('admin')->middleware('admin')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('admin.dashboard');

    Route::resource('posts', \App\Http\Controllers\Admin\PostController::class)
        ->except(['show'])
        ->names('admin.posts');

    Route::post('attachments', [\App\Http\Controllers\Admin\AttachmentController::class, 'store'])
        ->name('admin.attachments.store');

    Route::get('themes', [\App\Http\Controllers\Admin\ThemeController::class, 'index'])->name('admin.themes.index');
    Route::patch('themes', [\App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('admin.themes.update');
});

Route::get('/', fn () => redirect('/' . config('app.locale')));

Route::prefix('{locale}')
    ->where(['locale' => 'en|ro'])
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\PageController::class, 'home'])->name('home');
        Route::get('/about', [\App\Http\Controllers\PageController::class, 'about'])->name('about');
        Route::get('/services', [\App\Http\Controllers\PageController::class, 'services'])->name('services');
        Route::get('/contact', [\App\Http\Controllers\PageController::class, 'contact'])->name('contact');
        Route::post('/contact', [\App\Http\Controllers\PageController::class, 'contactSubmit'])
            ->middleware('throttle:5,1')
            ->name('contact.submit');

        // The blog feature is presented as "Articles" (menu label + public URL).
        // Route names stay blog.* to match BlogController and the blog/ views.
        Route::get('/articles', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog.index');
        Route::get('/articles/{slug}', [\App\Http\Controllers\BlogController::class, 'show'])->name('blog.show');

        // Back-compat: the feature used to live at /blog.
        Route::get('/blog', fn (string $locale) => redirect("/{$locale}/articles"));
        Route::get('/blog/{slug}', fn (string $locale, string $slug) => redirect("/{$locale}/articles/{$slug}"));
    });
