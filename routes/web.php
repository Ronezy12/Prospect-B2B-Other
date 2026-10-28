<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanyBulkController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyExportController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\SuppressionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/prospection');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('prospection')->name('pm.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::post('prospects/actions-groupees', CompanyBulkController::class)->name('companies.bulk');
        Route::get('prospects/export', CompanyExportController::class)->middleware('throttle:10,1')->name('companies.export');
        Route::post('prospects/{company}/convertir', [CompanyController::class, 'convert'])->name('companies.convert');
        Route::post('prospects/{company}/activites', [ActivityController::class, 'store'])->name('companies.activities.store');
        Route::resource('prospects', CompanyController::class)->parameters(['prospects' => 'company'])->names('companies');

        Route::post('contacts/{contact}/opposition', [SuppressionController::class, 'store'])->name('contacts.suppress');
        Route::resource('contacts', ContactController::class)->except('show');

        Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('imports/nouveau', [ImportController::class, 'create'])->name('imports.create');
        Route::post('imports', [ImportController::class, 'store'])->middleware('throttle:10,1')->name('imports.store');
        Route::get('imports/{import}/correspondance', [ImportController::class, 'mapping'])->name('imports.mapping');
        Route::post('imports/{import}/lancer', [ImportController::class, 'start'])->name('imports.start');
        Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
        Route::post('imports/{import}/annuler', [ImportController::class, 'cancel'])->name('imports.cancel');
        Route::get('imports/{import}/rapport', [ImportController::class, 'report'])->name('imports.report');

        Route::prefix('parametres')->name('settings.')->group(function () {
            Route::resource('utilisateurs', UserController::class)->except(['show', 'destroy'])
                ->parameters(['utilisateurs' => 'user'])->names('users');
        });
    });
});
