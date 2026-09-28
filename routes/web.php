<?php

use App\Http\Controllers\PetugasPreviewController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/preview/petugas/{page?}', PetugasPreviewController::class)
    ->withoutMiddleware([
        // GET-only, stateless preview; no session cookie or write endpoints.
        ValidateCsrfToken::class,
        StartSession::class,
        ShareErrorsFromSession::class,
    ])
    ->name('petugas.preview');

Route::get('/', function () {
    return view('welcome');
});
