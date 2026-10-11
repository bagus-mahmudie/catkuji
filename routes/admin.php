<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\AdminProductCategoryController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminBannerController;
use App\Http\Controllers\Admin\AdminNoteController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Web\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::middleware('guest')->group(function () {
            Route::get('/login', [AdminAuthenticatedSessionController::class, 'create'])
                ->name('login');

            Route::post('/login', [AdminAuthenticatedSessionController::class, 'store'])
                ->name('login.store');

            Route::post('/register', [AdminAuthenticatedSessionController::class, 'register'])
                ->name('register.store');
        });

        Route::middleware(['admin', 'verified', 'super_admin'])->group(function () {
            Route::get('/settings/admins', [AdminSettingController::class, 'admins'])
                ->name('settings.admins.index');

            Route::patch('/settings/admins/{user}', [AdminSettingController::class, 'updateAdmin'])
                ->name('settings.admins.update');
        });

        Route::middleware(['admin', 'verified'])->group(function () {
            Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])
                ->name('logout');

            Route::get('/', [AdminDashboardController::class, 'index'])
                ->name('dashboard');

            Route::get('/dashboard', [AdminDashboardController::class, 'index'])
                ->name('dashboard.index');

            Route::get('/dashboard/export', [AdminDashboardController::class, 'export'])
                ->name('dashboard.export');

            Route::post('/products/categories', [AdminProductCategoryController::class, 'store'])
                ->name('products.categories.store');

            Route::post('/products/categories/{category}/update', [AdminProductCategoryController::class, 'update'])
                ->name('products.categories.update');

            Route::delete('/products/categories/{category}', [AdminProductCategoryController::class, 'destroy'])
                ->name('products.categories.destroy');

            Route::post('/products/{product}/update', [AdminProductController::class, 'update'])
                ->name('products.update.multipart');

            Route::get('/products/analytics', [AdminProductController::class, 'analytics'])
                ->name('products.analytics');

            Route::resource('products', AdminProductController::class)
                ->except(['show', 'update']);

            Route::get('/payments', [AdminPaymentController::class, 'index'])
                ->name('payments.index');

            Route::get('/payments/export', [AdminPaymentController::class, 'export'])
                ->name('payments.export');

            Route::get('/orders', [AdminOrderController::class, 'index'])
                ->name('orders.index');

            Route::get('/orders/export', [AdminOrderController::class, 'export'])
                ->name('orders.export');

            Route::get('/users', [AdminUserController::class, 'index'])
                ->name('users.index');

            Route::get('/users/export', [AdminUserController::class, 'export'])
                ->name('users.export');

            Route::get('/users/csat', [AdminUserController::class, 'csatDetail'])
                ->name('users.csat.detail');

            Route::get('/users/csat/export', [AdminUserController::class, 'exportCsat'])
                ->name('users.csat.export');

            Route::get('/users/nps', [AdminUserController::class, 'npsDetail'])
                ->name('users.nps.detail');

            Route::get('/users/nps/export', [AdminUserController::class, 'exportNps'])
                ->name('users.nps.export');

            Route::prefix('marketing')->name('marketing.')->group(function () {
                Route::get('/banners/export', [AdminBannerController::class, 'export'])
                    ->name('banners.export');

                Route::post('/banners/{banner}/update', [AdminBannerController::class, 'update'])
                    ->name('banners.update.multipart');

                Route::resource('banners', AdminBannerController::class)
                    ->except(['show', 'create', 'edit', 'update']);
                    
                Route::resource('notes', AdminNoteController::class)
                    ->only(['index', 'store', 'update', 'destroy']);
            });

            Route::post('/shipments/{orderNo}/retry-booking', [ShipmentController::class, 'retryBooking'])
                ->name('shipments.retry-booking');

            Route::post('/shipments/{orderNo}/cancel', [ShipmentController::class, 'cancel'])
                ->name('shipments.cancel');
            
        });
    });
