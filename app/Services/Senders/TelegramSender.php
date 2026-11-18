<?php
declare(strict_types=1);

namespace App\Services\Senders;

use App\Models\Sender;
use Neili\{Client,Media,Settings};
use RuntimeException;

final class TelegramSender
{
    public function send(Sender $sender, string $path, string $caption, string $destination): int
    {
        if ($sender->type !== 'telegram' || !$sender->token) throw new RuntimeException('Telegram sender is not configured.');
        if (mb_strlen($caption) > 1024) throw new RuntimeException('Telegram caption exceeds 1,024 characters.');
        $api = (string) ($sender->api_url ?: config('realdarkmeme.bot_api_url'));
        if ($api === 'https://api.telegram.org/bot' && filesize($path) > 50*1024*1024)
            throw new RuntimeException('Video exceeds standard Telegram Bot API upload limit.');
        $client = new Client((new Settings())->setAccessToken($sender->token)->setApiUrl($api)->setTimeout(900,15));
        $response = $client->sendVideo($destination,new Media($path),caption:$caption)->await();
        $result = $response['result'] ?? $response;
        if (!is_array($result) || !is_numeric($result['message_id'] ?? null))
            throw new RuntimeException('Telegram did not return a message ID.');
        return (int) $result['message_id'];
    }
}
