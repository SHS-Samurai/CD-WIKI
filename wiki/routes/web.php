<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\GroupController as AdminGroupController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\ThemeController as AdminThemeController;
use App\Http\Controllers\Admin\TrashController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WebController as AdminWebController;
use App\Http\Controllers\Admin\WebPermissionController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleRevisionController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EditorImageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ThemeCssController;
use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;

Route::get('/installation', [InstallationController::class, 'create'])
    ->name('installation.create');
Route::post('/installation', [InstallationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('installation.store');

Route::get('/', HomeController::class)->name('home');

Route::get('/artikel', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/suche', SearchController::class)->name('search');
Route::get('/kategorien', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/kategorien/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/medien/{image:uuid}', [EditorImageController::class, 'show'])->name('editor-images.show');
Route::get('/theme/aktiv.css', ThemeCssController::class)->name('theme.css');

Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('verwaltung')->name('admin.')->middleware(['verified', 'admin'])->group(function () {
        Route::get('/webs', [AdminWebController::class, 'index'])->name('webs.index');
        Route::get('/webs/neu', [AdminWebController::class, 'create'])->name('webs.create');
        Route::post('/webs', [AdminWebController::class, 'store'])->name('webs.store');
        Route::get('/kategorien', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('/kategorien/neu', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('/kategorien', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('/kategorien/{category:slug}/bearbeiten', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/kategorien/{category:slug}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/kategorien/{category:slug}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
        Route::get('/benutzer', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/benutzer/neu', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/benutzer', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/benutzer/{user}/bearbeiten', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::patch('/benutzer/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::get('/gruppen', [AdminGroupController::class, 'index'])->name('groups.index');
        Route::get('/gruppen/neu', [AdminGroupController::class, 'create'])->name('groups.create');
        Route::post('/gruppen', [AdminGroupController::class, 'store'])->name('groups.store');
        Route::get('/gruppen/{group}/bearbeiten', [AdminGroupController::class, 'edit'])->name('groups.edit');
        Route::patch('/gruppen/{group}', [AdminGroupController::class, 'update'])->name('groups.update');
        Route::get('/theme', [AdminThemeController::class, 'edit'])->name('theme.edit');
        Route::patch('/theme', [AdminThemeController::class, 'update'])->name('theme.update');
        Route::get('/einstellungen', [SystemSettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('/einstellungen', [SystemSettingsController::class, 'update'])->name('settings.update');
        Route::get('/auditlog', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/auditlog/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');
        Route::get('/papierkorb', [TrashController::class, 'index'])->name('trash.index');
        Route::post('/papierkorb/artikel/{article}/wiederherstellen', [TrashController::class, 'restoreArticle'])->name('trash.articles.restore');
        Route::post('/papierkorb/anhaenge/{attachment}/wiederherstellen', [TrashController::class, 'restoreAttachment'])->name('trash.attachments.restore');
    });

    Route::prefix('verwaltung')->name('admin.')->middleware(['verified', 'web.manage'])->scopeBindings()->group(function () {
        Route::get('/webs/{web:slug}/bearbeiten', [AdminWebController::class, 'edit'])->name('webs.edit');
        Route::patch('/webs/{web:slug}', [AdminWebController::class, 'update'])->name('webs.update');
        Route::get('/webs/{web:slug}/rechte', [WebPermissionController::class, 'index'])->name('webs.permissions.index');
        Route::post('/webs/{web:slug}/rechte', [WebPermissionController::class, 'store'])->name('webs.permissions.store');
        Route::delete('/webs/{web:slug}/rechte/{permission}', [WebPermissionController::class, 'destroy'])->name('webs.permissions.destroy');
    });
});

Route::scopeBindings()->group(function () {
    Route::get('/w/{web:slug}', [WebController::class, 'show'])->name('webs.show');
    Route::get('/w/{web:slug}/artikel/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');
    Route::get('/w/{web:slug}/artikel/{article:slug}/versionen', [ArticleRevisionController::class, 'index'])->name('articles.revisions.index');
    Route::get('/w/{web:slug}/artikel/{article:slug}/versionen/vergleich', [ArticleRevisionController::class, 'compare'])->name('articles.revisions.compare');
    Route::get('/w/{web:slug}/artikel/{article:slug}/versionen/{revision:revision_number}', [ArticleRevisionController::class, 'show'])->name('articles.revisions.show');
    Route::get('/w/{web:slug}/artikel/{article:slug}/anhaenge/{attachment:uuid}', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::get('/w/{web:slug}/artikel/{article:slug}/anhaenge/{attachment:uuid}/versionen/{revision:revision_number}', [AttachmentController::class, 'downloadRevision'])->name('attachments.revisions.download');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/w/{web:slug}/neu', [ArticleController::class, 'create'])->name('articles.create');
        Route::post('/w/{web:slug}/artikel', [ArticleController::class, 'store'])->name('articles.store');
        Route::get('/w/{web:slug}/artikel/{article:slug}/bearbeiten', [ArticleController::class, 'edit'])->name('articles.edit');
        Route::patch('/w/{web:slug}/artikel/{article:slug}', [ArticleController::class, 'update'])->name('articles.update');
        Route::delete('/w/{web:slug}/artikel/{article:slug}', [ArticleController::class, 'destroy'])->name('articles.destroy');
        Route::post('/w/{web:slug}/bilder', [EditorImageController::class, 'store'])->name('editor-images.store');
        Route::post('/w/{web:slug}/artikel/{article:slug}/kommentare', [CommentController::class, 'store'])->name('comments.store');
        Route::delete('/w/{web:slug}/artikel/{article:slug}/kommentare/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('/w/{web:slug}/artikel/{article:slug}/anhaenge', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('/w/{web:slug}/artikel/{article:slug}/anhaenge/{attachment:uuid}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::post('/w/{web:slug}/artikel/{article:slug}/versionen/{revision:revision_number}/wiederherstellen', [ArticleRevisionController::class, 'restore'])->name('articles.revisions.restore');
    });
});

require __DIR__.'/auth.php';
