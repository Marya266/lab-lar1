<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserMessenger;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $message;
    protected $messengerIds;

    public function __construct($userId, $message, $messengerIds = null)
    {
        $this->userId = $userId;
        $this->message = $message;
        $this->messengerIds = $messengerIds;
    }

    public function handle(NotificationService $notificationService)
    {
        $startTime = microtime(true);
        
        try {
            $user = User::with(['userMessengers.messenger'])->findOrFail($this->userId);
            
            $userMessengers = $user->userMessengers()
                                  ->with('messenger')
                                  ->where('status', 'confirmed')
                                  ->where('notifications_enabled', true)
                                  ->when($this->messengerIds, function ($query) {
                                      return $query->whereIn('messenger_id', $this->messengerIds);
                                  })
                                  ->get();

            $results = [];
            
            foreach ($userMessengers as $userMessenger) {
                try {
                    $result = $notificationService->sendToMessenger(
                        $userMessenger->messenger,
                        $userMessenger->messenger_user_id,
                        $this->message
                    );
                    
                    $results[] = [
                        'messenger' => $userMessenger->messenger->name,
                        'user_id' => $userMessenger->messenger_user_id,
                        'success' => $result['success'],
                        'response' => $result['response'] ?? null,
                        'error' => $result['error'] ?? null
                    ];
                    
                } catch (\Exception $e) {
                    $results[] = [
                        'messenger' => $userMessenger->messenger->name,
                        'user_id' => $userMessenger->messenger_user_id,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('Уведомления отправлены', [
                'user_id' => $this->userId,
                'message_length' => strlen($this->message),
                'messengers_count' => count($results),
                'execution_time_ms' => $executionTime,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ошибка при отправке уведомлений', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
