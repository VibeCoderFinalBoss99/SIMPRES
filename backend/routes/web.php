<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\RecapController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\SubmissionController;
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

        Route::get('/pengajuan', [SubmissionController::class, 'index'])->name('admin.pengajuan.index');
        // {type}: izin, koreksi, atau tugas (dicek di SubmissionController).
        Route::get('/pengajuan/{type}/{id}', [SubmissionController::class, 'show'])->whereNumber('id')->name('admin.pengajuan.show');
        Route::get('/pengajuan/{type}/{id}/file', [SubmissionController::class, 'file'])->whereNumber('id')->name('admin.pengajuan.file');
        Route::post('/pengajuan/{type}/{id}/keputusan', [SubmissionController::class, 'review'])->whereNumber('id')->name('admin.pengajuan.review');

        Route::get('/rekap', [RecapController::class, 'index'])->name('admin.rekap.index');
        Route::get('/rekap/csv', [RecapController::class, 'csv'])->name('admin.rekap.csv');
    });
});
