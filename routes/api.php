<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DamageReportController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\PetugasController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- 1. Public Authentication & Visitors Routes ---
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Public facility catalog & availability (Visitors - US 1, US 2)
Route::get('/facilities', [FacilityController::class, 'index']);
Route::get('/facilities/{id}', [FacilityController::class, 'show']);
Route::get('/facilities/{id}/availability', [FacilityController::class, 'availability']);

// --- 2. Authenticated Routes (Common) ---
Route::middleware(['auth:sanctum', 'token.idle'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/reservations/{id}/supporting-file', [ReservationController::class, 'supportingFile']);
    Route::get('/reports/{id}/photo', [DamageReportController::class, 'photo']);

    // --- 3. Pengguna / User Routes (US 3, US 4, US 5, US 6, US 7) ---
    Route::middleware('role:pengguna,user')->group(function () {
        Route::get('/user/reservations', [ReservationController::class, 'myReservations']);
        Route::post('/reservations', [ReservationController::class, 'store']);
        Route::post('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);

        Route::get('/user/reports', [DamageReportController::class, 'myReports']);
        Route::post('/reports', [DamageReportController::class, 'store']);
    });

    // --- 4. Petugas & Admin Operational Routes (US 8, US 9, US 10, US 11, US 12) ---
    Route::middleware('role:petugas,admin')->group(function () {
        Route::get('/petugas/dashboard', [PetugasController::class, 'dashboard']);
        Route::get('/petugas/queue', [ReservationController::class, 'queue']);
        Route::post('/petugas/reservations/{id}/approve', [ReservationController::class, 'approve']);
        Route::post('/petugas/reservations/{id}/reject', [ReservationController::class, 'reject']);
        Route::post('/petugas/reservations/{id}/emergency-cancel', [ReservationController::class, 'emergencyCancel']);

        Route::get('/petugas/damage-reports', [DamageReportController::class, 'index']);
        Route::match(['post', 'patch'], '/petugas/damage-reports/{id}/status', [DamageReportController::class, 'updateStatus']);

        Route::match(['post', 'patch'], '/petugas/facilities/{id}/maintenance', [FacilityController::class, 'toggleStatus']);
    });

    // --- 5. Admin System Administration Routes (US 13, US 14, US 15, US 16, US 17) ---
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/users', [AdminController::class, 'users']);
        Route::post('/admin/users', [AdminController::class, 'createUser']);
        Route::match(['post', 'patch'], '/admin/users/{id}/verify', [AdminController::class, 'verifyUser']);
        Route::match(['post', 'patch'], '/admin/users/{id}/reject', [AdminController::class, 'rejectUser']);
        Route::match(['post', 'patch'], '/admin/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus']);

        Route::post('/admin/facilities', [FacilityController::class, 'store']);
        Route::match(['post', 'put'], '/admin/facilities/{id}', [FacilityController::class, 'update']);
        Route::match(['post', 'patch'], '/admin/facilities/{id}/toggle-status', [FacilityController::class, 'toggleStatus']);
        Route::delete('/admin/facilities/{id}', [FacilityController::class, 'destroy']);

        Route::get('/admin/rekap', [AdminController::class, 'rekap']);
    });
});

Route::get('/admin/export/{format}', [AdminController::class, 'exportRekap'])
    ->middleware(['auth:sanctum', 'token.idle', 'role:admin']);