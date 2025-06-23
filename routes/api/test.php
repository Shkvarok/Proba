<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Models\Payment;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_middleware', 'verified')
])->group(function () {
    Route::get('/payments/course/{courseId}/success', [PaymentController::class, 'paymentSuccess']);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_middleware', 'verified')
])->group(function () {
    Route::get('/payments/course/{courseId}/success', [PaymentController::class, 'paymentSuccess']);
});

Route::get('/test-liqpay-form/{paymentId}', [PaymentController::class, 'testLiqpayForm']); 