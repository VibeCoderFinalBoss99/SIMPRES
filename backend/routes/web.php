<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ShiftController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin Web: login session, hanya role admin.
Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login']);
    });

    // no_store: setelah logout, tombol Back browser tidak menampilkan halaman admin dari cache.
    Route::middleware(['auth', 'admin', 'cache.headers:no_store;no_cache;must_revalidate;private'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

        Route::get('/', DashboardController::class)->name('admin.dashboard');

        Route::resource('pegawai', EmployeeController::class)
            ->except(['show', 'destroy'])
            ->parameters(['pegawai' => 'user'])
            ->names('admin.pegawai');
        Route::patch('/pegawai/{user}/status', [EmployeeController::class, 'toggleActive'])->name('admin.pegawai.toggle');

        Route::get('/lokasi', [LocationController::class, 'edit'])->name('admin.lokasi.edit');
        Route::put('/lokasi', [LocationController::class, 'update'])->name('admin.lokasi.update');

        Route::resource('shift', ShiftController::class)->except(['show'])->names('admin.shift');

        Route::get('/jadwal', [ScheduleController::class, 'index'])->name('admin.jadwal.index');
        Route::post('/jadwal', [ScheduleController::class, 'store'])->name('admin.jadwal.store');
        Route::put('/jadwal/bulan', [ScheduleController::class, 'updateMonth'])->name('admin.jadwal.month');
    });
});
