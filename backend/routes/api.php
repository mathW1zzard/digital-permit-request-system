<?php

use App\Http\Controllers\AdminExceptionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PermitController;
use App\Models\PermitType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Every route here already passed CorrelationId, SecurityHeaders and RequireHttps (see bootstrap/app.php).
Route::post('/login', [AuthController::class, 'login']);

// Week 16: readiness for monitoring (liveness stays at /up). Public; statuses, ages and counts only.
// No app-level throttle: the limiter's counters live in the database, so it would fail exactly when
// this check must answer 503. It is rate-limited at nginx instead (deploy/nginx/permit-staging.conf).
Route::get('/health', [HealthController::class, 'ready']);

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', fn (Request $request) => ['data' => $request->user()->only(['id', 'name', 'role'])]);
    Route::get('/permit-types', fn () => ['data' => PermitType::orderBy('name')->get(['id', 'code', 'name', 'validity_days'])]);

    Route::get('/permits', [PermitController::class, 'index']);
    Route::post('/permits', [PermitController::class, 'store'])->middleware('role:requester');
    Route::get('/permits/{permit:reference}', [PermitController::class, 'show']);
    Route::post('/permits/{permit:reference}/request-revision', [PermitController::class, 'requestRevision']);
    Route::post('/permits/{permit:reference}/resubmit', [PermitController::class, 'resubmit']);
    Route::post('/permits/{permit:reference}/endorse', [PermitController::class, 'endorse']);
    Route::post('/permits/{permit:reference}/approve', [PermitController::class, 'approve']);
    Route::post('/permits/{permit:reference}/reject', [PermitController::class, 'reject']);
    Route::get('/permits/{permit:reference}/verify', [PermitController::class, 'verify'])->middleware('role:inspector,admin');

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/exceptions', [AdminExceptionController::class, 'index']);
        Route::post('/notifications/{notification}/requeue', [AdminExceptionController::class, 'requeue']);
    });
});
