<?php

namespace App\Http\Controllers;

use App\Models\Messenger;
use App\Models\User;
use App\Models\UserMessenger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MessengerController extends Controller
{
    public function index()
    {
        $messengers = Messenger::forEnvironment()
                              ->with('userMessengers')
                              ->get();
        
        return response()->json([
            'messengers' => $messengers,
            'environment' => app()->environment()
        ]);
    }

    public function linkUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'messenger_id' => 'required|exists:messengers,id',
            'messenger_user_id' => 'required|string|max:255'
        ]);

        $userMessenger = UserMessenger::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'messenger_id' => $request->messenger_id
            ],
            [
                'messenger_user_id' => $request->messenger_user_id,
                'status' => 'pending',
                'verification_code' => Str::random(6),
                'verification_expires_at' => now()->addHours(24),
                'notifications_enabled' => true
            ]
        );

        return response()->json([
            'message' => 'Пользователь привязан к мессенджеру',
            'user_messenger' => $userMessenger,
            'verification_code' => $userMessenger->verification_code
        ]);
    }

    public function confirmUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'messenger_id' => 'required|exists:messengers,id',
            'verification_code' => 'required|string'
        ]);

        $userMessenger = UserMessenger::where('user_id', $request->user_id)
                                    ->where('messenger_id', $request->messenger_id)
                                    ->where('verification_code', $request->verification_code)
                                    ->first();

        if (!$userMessenger) {
            return response()->json(['error' => 'Неверный код верификации'], 400);
        }

        if (!$userMessenger->isVerificationCodeValid()) {
            return response()->json(['error' => 'Код верификации истек'], 400);
        }

        $userMessenger->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'verification_code' => null,
            'verification_expires_at' => null
        ]);

        return response()->json([
            'message' => 'Пользователь успешно подтвержден',
            'user_messenger' => $userMessenger
        ]);
    }

    public function getUserMessengers($userId)
    {
        $user = User::with(['userMessengers.messenger'])
                   ->findOrFail($userId);

        return response()->json([
            'user' => $user,
            'messengers' => $user->userMessengers
        ]);
    }

    public function toggleNotifications(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'messenger_id' => 'required|exists:messengers,id',
            'enabled' => 'required|boolean'
        ]);

        $userMessenger = UserMessenger::where('user_id', $request->user_id)
                                    ->where('messenger_id', $request->messenger_id)
                                    ->first();

        if (!$userMessenger) {
            return response()->json(['error' => 'Связь не найдена'], 404);
        }

        $userMessenger->update([
            'notifications_enabled' => $request->enabled
        ]);

        return response()->json([
            'message' => 'Настройки уведомлений обновлены',
            'user_messenger' => $userMessenger
        ]);
    }
}
