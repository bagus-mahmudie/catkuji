<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\BiteshipWebhookController;
use App\Http\Controllers\Web\CategoryPageController;
use App\Http\Controllers\Web\DrawController;
use App\Http\Controllers\Web\GachaEventController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\InventoryPackController;
use App\Http\Controllers\Web\InventorySellbackController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\ProfileAddressController;
use App\Http\Controllers\Web\ProfileOrderController;
use App\Http\Controllers\Web\ProfilePageController;
use App\Http\Controllers\Web\ProfileVoucherController;
use App\Http\Controllers\Web\ShipmentController;
use App\Http\Controllers\Web\TransactionController;
use App\Http\Controllers\Web\TransactionReceiptController;
use App\Http\Controllers\Web\WalletController;
use App\Http\Controllers\Web\WishlistController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\NpsFeedbackController;
use App\Http\Controllers\Web\CsatFeedbackController;
use App\Http\Controllers\Web\TokenController;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/auth.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('profile')->name('profile.')->group(function () {
    Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
    Route::patch('/edit', [ProfileController::class, 'update'])->name('update');
    Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('avatar.update');
    Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');

    Route::get('/token', [ProfilePageController::class, 'token'])->name('token');

    Route::get('/address', [ProfileAddressController::class, 'edit'])->name('address.edit');
    Route::put('/address', [ProfileAddressController::class, 'update'])->name('address.update');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
    Route::get('/orders', [ProfileOrderController::class, 'index'])->name('orders');
    Route::get('/voucher', [ProfileVoucherController::class, 'index'])->name('voucher');
    Route::get('/language', [ProfilePageController::class, 'language'])->name('language');
    Route::get('/terms', [ProfilePageController::class, 'terms'])->name('terms');
    Route::get('/privacy', [ProfilePageController::class, 'privacy'])->name('privacy');
    });
    
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/topup', [PaymentController::class, 'store'])->name('wallet.topup.store');
    Route::post('/wallet/topup/sync', [PaymentController::class, 'sync'])->name('wallet.topup.sync');

    Route::get('/token', [TokenController::class, 'index'])->name('token.index');
    Route::get('/token/{prizeSlug}', [TokenController::class, 'show'])->name('token.show');
    Route::post('/token/{prizeSlug}/redeem', [TokenController::class, 'redeem'])->name('token.redeem');
    
    Route::post('/events/{event:slug}/draw', [DrawController::class, 'store'])
        ->name('events.draw.store');

    Route::get('/inventory', [InventoryController::class, 'index'])
        ->name('inventory.index');

    Route::get('/inventory/pack', [InventoryPackController::class, 'index'])
        ->name('inventory.pack.index');
    Route::post('/inventory/pack/{pack}/open', [InventoryPackController::class, 'open'])
        ->name('inventory.pack.open');

    Route::post('/inventory/sellback/preview', [InventorySellbackController::class, 'preview'])
        ->name('inventory.sellback.preview');
    Route::get('/inventory/sellback/confirm', [InventorySellbackController::class, 'confirm'])
        ->name('inventory.sellback.confirm');
    Route::post('/inventory/sellback/checkout', [InventorySellbackController::class, 'checkout'])
        ->name('inventory.sellback.checkout');

    Route::get('/shipment', [ShipmentController::class, 'index'])->name('shipment.index');
    Route::post('/shipment/preview', [ShipmentController::class, 'preview'])->name('shipment.preview');
    Route::get('/shipment/confirm', [ShipmentController::class, 'confirm'])->name('shipment.confirm');
    Route::post('/shipment/rates', [ShipmentController::class, 'rates'])->name('shipment.rates');
    Route::post('/shipment/checkout', [ShipmentController::class, 'checkout'])->name('shipment.checkout');
    Route::get('/shipment/{orderNo}', [ShipmentController::class, 'show'])->name('shipment.show');

    Route::get('/transactions', [TransactionController::class, 'index'])
        ->name('transactions.index');
    Route::get('/transactions/topup/{paymentTransaction}/receipt', [TransactionReceiptController::class, 'topup'])
        ->name('transactions.receipt.topup');

    Route::post('/wishlist/{event}', [WishlistController::class, 'store'])
        ->name('wishlist.store');
    Route::delete('/wishlist/{event}', [WishlistController::class, 'destroy'])
        ->name('wishlist.destroy');
});

Route::post('/payments/midtrans/callback', [PaymentController::class, 'callback'])
    ->name('payments.midtrans.callback');

Route::post('/shipping/biteship/webhook', BiteshipWebhookController::class)
    ->name('shipping.biteship.webhook');

Route::get('/events/{event:slug}', [GachaEventController::class, 'show'])
    ->name('events.show');

Route::get('/categories/{category:slug}', [CategoryPageController::class, 'show'])
    ->name('categories.show');

Route::middleware(['auth'])->group(function () {
    Route::get('/nps-feedback/eligibility', [NpsFeedbackController::class, 'eligibility'])
        ->name('nps-feedback.eligibility');

    Route::post('/nps-feedback', [NpsFeedbackController::class, 'store'])
        ->name('nps-feedback.store');

    Route::post('/csat-feedback', [CsatFeedbackController::class, 'store'])
        ->name('csat-feedback.store');

    Route::get('/csat-feedback/eligibility', [CsatFeedbackController::class, 'eligibility'])
    ->name('csat-feedback.eligibility');
});


require __DIR__ . '/admin.php';