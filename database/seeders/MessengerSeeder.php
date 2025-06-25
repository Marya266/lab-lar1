<?php

namespace Database\Seeders;

use App\Models\Messenger;
use Illuminate\Database\Seeder;

class MessengerSeeder extends Seeder
{
    public function run()
    {
        $messengers = [
            [
                'name' => 'Telegram',
                'description' => 'Мессенджер Telegram для отправки уведомлений',
                'environment' => 'local',
                'token_env_variable' => 'TELEGRAM_BOT_TOKEN',
                'api_endpoint' => 'https://api.telegram.org/bot{token}/sendMessage',
                'is_active' => true
            ],
            [
                'name' => 'Slack',
                'description' => 'Корпоративный мессенджер Slack',
                'environment' => 'dev',
                'token_env_variable' => 'SLACK_BOT_TOKEN',
                'api_endpoint' => 'https://slack.com/api/chat.postMessage',
                'is_active' => true
            ],
            [
                'name' => 'Discord',
                'description' => 'Игровой мессенджер Discord',
                'environment' => 'prod',
                'token_env_variable' => 'DISCORD_BOT_TOKEN',
                'api_endpoint' => 'https://discord.com/api/channels/{channel_id}/messages',
                'is_active' => false
            ]
        ];

        foreach ($messengers as $messenger) {
            Messenger::create($messenger);
        }
    }
}
