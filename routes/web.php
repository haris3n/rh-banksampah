<?php

use App\Http\Controllers\PetugasPreviewController;
use App\Http\Controllers\PetugasController;
use App\Http\Middleware\ActivePetugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/rumah-hijau-logo.png', function () {
    return response()->file(resource_path('photos/petugas/image.png'));
})->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class])->name('petugas.logo');

Route::get('/preview/petugas/{page?}', PetugasPreviewController::class)
    ->withoutMiddleware([
        // GET-only, stateless preview; no session cookie or write endpoints.
        ValidateCsrfToken::class,
        StartSession::class,
        ShareErrorsFromSession::class,
    ])
    ->name('petugas.preview');

Route::get('/', function () {
    return redirect('/petugas');
});

Route::view('/petugas/login', 'petugas.operational', ['screen' => 'login'])->name('login');
Route::post('/petugas/login', [PetugasController::class, 'login'])->middleware('throttle:5,1');
Route::middleware(ActivePetugas::class)->prefix('petugas')->group(function () {
    Route::get('/nasabah-search', [PetugasController::class, 'search']);
    Route::get('/nasabah', [PetugasController::class, 'customers']);
    Route::get('/nasabah/{id}', [PetugasController::class, 'customers'])->whereNumber('id');
    Route::get('/dashboard', [\App\Http\Controllers\PetugasReportController::class, 'show']);
    Route::get('/pengepul', [\App\Http\Controllers\PetugasReportController::class, 'show']);
    Route::get('/export', [\App\Http\Controllers\PetugasReportController::class, 'export']);
    Route::get('/', [PetugasController::class, 'index']);
    Route::get('/setoran-baru', [PetugasController::class, 'form']);
    Route::post('/setoran', [PetugasController::class, 'save']);
    Route::get('/setoran/{id}', [PetugasController::class, 'show'])->whereNumber('id');
    Route::get('/setoran/{id}/edit', [PetugasController::class, 'form'])->whereNumber('id');
    Route::put('/setoran/{id}', [PetugasController::class, 'save'])->whereNumber('id');
    Route::post('/setoran/{id}/finish', [PetugasController::class, 'finish'])->whereNumber('id');
    Route::post('/logout', function (Request $request) {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    });
});
