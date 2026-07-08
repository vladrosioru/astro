<?php

use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AuthController::class, 'show'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.attempt');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

Route::prefix('admin')->middleware('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    Route::resource('posts', PostController::class)
        ->except(['show'])
        ->names('admin.posts');

    Route::post('attachments', [AttachmentController::class, 'store'])
        ->name('admin.attachments.store');

    Route::get('themes', [ThemeController::class, 'index'])->name('admin.themes.index');
    Route::patch('themes', [ThemeController::class, 'update'])->name('admin.themes.update');
});

Route::get('/', fn () => redirect('/'.config('app.locale')));

Route::prefix('{locale}')
    ->where(['locale' => 'en|ro'])
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', [PageController::class, 'home'])->name('home');
        Route::get('/about', [PageController::class, 'about'])->name('about');
        Route::get('/services', [PageController::class, 'services'])->name('services');
        Route::get('/contact', [PageController::class, 'contact'])->name('contact');
        Route::post('/contact', [PageController::class, 'contactSubmit'])
            ->middleware('throttle:5,1')
            ->name('contact.submit');

        // The blog feature is presented as "Journal" (menu label + public URL).
        // Route names stay blog.* to match BlogController and the blog/ views.
        Route::get('/journal', [BlogController::class, 'index'])->name('blog.index');
        Route::get('/journal/{slug}', [BlogController::class, 'show'])->name('blog.show');

        // Back-compat: the feature used to live at /blog, then /articles.
        Route::get('/blog', fn (string $locale) => redirect("/{$locale}/journal"));
        Route::get('/blog/{slug}', fn (string $locale, string $slug) => redirect("/{$locale}/journal/{$slug}"));
        Route::get('/articles', fn (string $locale) => redirect("/{$locale}/journal"));
        Route::get('/articles/{slug}', fn (string $locale, string $slug) => redirect("/{$locale}/journal/{$slug}"));
    });
