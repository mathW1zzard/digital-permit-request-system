<?php

use App\Http\Controllers\AdminExceptionController;
use App\Http\Controllers\PermitController;
use App\Models\PermitType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Every route here already passed CorrelationId and DemoAuth (see bootstrap/app.php).
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
