<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function testLiqpayForm($paymentId)
    {
        $payment = Payment::findOrFail($paymentId);

        $user = User::find($payment->user_id);

        return response()->json([
            'success' => true,
            'message' => 'Оплата знайдена!',
            'payment' => $payment,
            'user' => $user ? $user->only(['id', 'email', 'name']) : null,
        ]);
    }
} 