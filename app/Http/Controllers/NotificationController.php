<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotificationJob;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function sendNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'required|string|max:4000',
            'messenger_ids' => 'nullable|array',
            'messenger_ids.*' => 'exists:messengers,id',
            'async' => 'boolean'
        ]);

        $isAsync = $request->boolean('async', true);

        if ($isAsync) {
            // Асинхронная отправка через Job
            SendNotificationJob::dispatch(
                $request->user_id,
                $request->message,
                $request->messenger_ids
            );

            return response()->json([
                'message' => 'Уведомление поставлено в очередь на отправку',
                'async' => true,
                'status' => 'queued'
            ]);
        } else {
            // Синхронная отправка
            $job = new SendNotificationJob(
                $request->user_id,
                $request->message,
                $request->messenger_ids
            );
            
            $job->handle($this->notificationService);

            return response()->json([
                'message' => 'Уведомление отправлено',
                'async' => false,
                'status' => 'sent'
            ]);
        }
    }

    public function testConnection(Request $request)
    {
        $request->validate([
            'messenger_id' => 'required|exists:messengers,id',
            'recipient_id' => 'required|string'
        ]);

        $messenger = \App\Models\Messenger::findOrFail($request->messenger_id);
        
        $result = $this->notificationService->validateMessengerConnection(
            $messenger,
            $request->recipient_id
        );

        return response()->json([
            'messenger' => $messenger->name,
            'connection_test' => $result
        ]);
    }

    public function broadcastNotification(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:4000',
            'messenger_ids' => 'nullable|array',
            'messenger_ids.*' => 'exists:messengers,id',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $userQuery = User::query();
        
        if ($request->has('user_ids')) {
            $userQuery->whereIn('id', $request->user_ids);
        }

        $users = $userQuery->get();
        $jobsCount = 0;

        foreach ($users as $user) {
            SendNotificationJob::dispatch(
                $user->id,
                $request->message,
                $request->messenger_ids
            );
            $jobsCount++;
        }

        return response()->json([
            'message' => 'Массовая рассылка запущена',
            'users_count' => $users->count(),
            'jobs_queued' => $jobsCount
        ]);
    }
}
