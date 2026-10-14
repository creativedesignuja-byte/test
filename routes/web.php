<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dashboard'));
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth', ['register' => false])->name('login');
    Route::view('/register', 'auth', ['register' => true]);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    foreach (['/dashboard', '/projects', '/projects/{project}', '/projects/{project}/goal', '/projects/{project}/stages', '/projects/{project}/members', '/tasks', '/tasks/{task}', '/labels', '/settings'] as $path) {
        Route::get($path, function () {
            return view('workspace');
        });
    }
    Route::get('/attachments/{attachment}', [WorkspaceController::class, 'download']);
    Route::prefix('api')->middleware('throttle:120,1')->controller(WorkspaceController::class)->group(function () {
        Route::get('/workspace', 'data');
        Route::post('/projects', 'project');
        Route::put('/projects/{project}/goal', 'goal');
        Route::post('/projects/{project}/members', 'member');
        Route::post('/projects/{project}/stages', 'stage');
        Route::put('/projects/{project}/stages/{kind}/order', 'order')->whereIn('kind', ['product', 'member']);
        Route::match(['put', 'delete'], '/projects/{project}/stages/{kind}/{id}', 'updateStage')->whereIn('kind', ['product', 'member']);
        Route::get('/tasks/{task}', 'taskDetail');
        Route::post('/tasks', 'saveTask');
        Route::put('/tasks/{task}', 'saveTask');
        Route::patch('/tasks/{task}/complete', 'complete');
        Route::delete('/tasks/{task}', 'removeTask');
        Route::post('/labels', 'label');
        Route::match(['put', 'delete'], '/labels/{label}', 'label');
        Route::post('/tasks/{task}/comments', 'comment');
        Route::match(['put', 'delete'], '/comments/{comment}', 'editComment');
        Route::post('/tasks/{task}/attachments', 'upload');
        Route::delete('/attachments/{attachment}', 'removeAttachment');
        Route::post('/notices/read','notices');
        Route::put('/settings','settings');
    });
});
