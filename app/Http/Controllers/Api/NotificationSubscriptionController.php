<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationSubscriptionController extends Controller
{
    // Підписатися (авторизований або email)
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|max:50',
            'email' => 'nullable|email',
        ]);
        $user = Auth::user();
        $userId = $user ? $user->id : null;
        $email = $data['email'] ?? ($user ? $user->email : null);
        if (!$userId && !$email) {
            return response()->json(['success' => false, 'message' => 'Потрібен email або авторизація'], 422);
        }
        $subscription = NotificationSubscription::updateOrCreate(
            [ 'user_id' => $userId, 'email' => $email, 'type' => $data['type'] ],
            [ 'is_active' => true ]
        );
        return response()->json(['success' => true, 'subscription' => $subscription]);
    }

    // Відписатися
    public function unsubscribe(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|max:50',
            'email' => 'nullable|email',
        ]);
        $user = Auth::user();
        $userId = $user ? $user->id : null;
        $email = $data['email'] ?? ($user ? $user->email : null);
        if (!$userId && !$email) {
            return response()->json(['success' => false, 'message' => 'Потрібен email або авторизація'], 422);
        }
        $subscription = NotificationSubscription::where([
            'user_id' => $userId,
            'email' => $email,
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
} 