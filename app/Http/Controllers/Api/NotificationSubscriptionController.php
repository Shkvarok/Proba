<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationSubscriptionController extends Controller
{
    // Підписатися (тільки авторизований користувач)
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|max:50',
        ]);
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Неавторизовано'], 401);
        }
        $subscription = NotificationSubscription::updateOrCreate(
            [ 'user_id' => $user->id, 'email' => $user->email, 'type' => $data['type'] ],
            [ 'is_active' => true ]
        );
        return response()->json(['success' => true, 'subscription' => $subscription]);
    }

    // Відписатися (тільки авторизований користувач)
    public function unsubscribe(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|max:50',
        ]);
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Неавторизовано'], 401);
        }
        $subscription = NotificationSubscription::where([
            'user_id' => $user->id,
            'email' => $user->email,
            'type' => $data['type'],
        ])->first();
        if ($subscription) {
            $subscription->is_active = false;
            $subscription->save();
            return response()->json(['success' => true, 'message' => 'Відписка успішна']);
        }
        return response()->json(['success' => false, 'message' => 'Підписку не знайдено'], 404);
    }

    // Мої підписки (авторизований)
    public function getMySubscriptions()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Неавторизовано'], 401);
        }
        $subs = NotificationSubscription::where('user_id', $user->id)->get();
        return response()->json(['success' => true, 'subscriptions' => $subs]);
    }

    // Оновити підписку (наприклад, змінити тип або email)
    public function updateSubscription(Request $request, $id)
    {
        $data = $request->validate([
            'type' => 'sometimes|string|max:50',
            'email' => 'sometimes|email',
            'is_active' => 'sometimes|boolean',
        ]);
        $subscription = NotificationSubscription::findOrFail($id);
        $subscription->update($data);
        return response()->json(['success' => true, 'subscription' => $subscription]);
    }

    /**
     * Надіслати email всім підписаним на певний тип розсилки
     */
    public function sendToAll(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|string|max:50',
        ]);

        $subscriptions = NotificationSubscription::where('type', $data['type'])
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->unique();

        $sent = 0;
        foreach ($subscriptions as $email) {
            try {
                \Mail::raw($data['message'], function ($msg) use ($email, $data) {
                    $msg->to($email)->subject($data['subject']);
                });
                $sent++;
            } catch (\Exception $e) {
                // Можна залогувати помилку
            }
        }

        return response()->json([
            'success' => true,
            'sent_count' => $sent,
            'emails' => $subscriptions,
        ]);
    }
} 