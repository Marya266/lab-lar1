<?php

namespace App\Services;

use App\Models\Messenger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function sendToMessenger(Messenger $messenger, string $recipientId, string $message): array
    {
        $token = $messenger->getTokenFromEnv();
        
        if (!$token) {
            throw new \Exception("Токен для мессенджера {$messenger->name} не найден в переменных окружения");
        }

        try {
            $response = Http::timeout(30)
                          ->withHeaders([
                              'Authorization' => "Bearer {$token}",
                              'Content-Type' => 'application/json'
                          ])
                          ->post($messenger->api_endpoint, [
                              'chat_id' => $recipientId,
                              'text' => $message,
                              'parse_mode' => 'HTML'
                          ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'response' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: " . $response->body()
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function validateMessengerConnection(Messenger $messenger, string $recipientId): array
    {
        $token = $messenger->getTokenFromEnv();
        
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Токен не найден'
            ];
        }

        try {
            $testMessage = "✅ Тестовое сообщение для проверки соединения";
            return $this->sendToMessenger($messenger, $recipientId, $testMessage);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
