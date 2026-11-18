<?php
declare(strict_types=1);

namespace App\Services\Senders;

use App\Models\Sender;
use RuntimeException;

final class SenderManager
{
    public function send(Sender $sender, string $path, string $caption, string $destination): int
    {
        return match ($sender->type) {
            'telegram' => app(TelegramSender::class)->send($sender,$path,$caption,$destination),
            default => throw new RuntimeException('Unsupported sender type: '.$sender->type),
        };
    }
}
