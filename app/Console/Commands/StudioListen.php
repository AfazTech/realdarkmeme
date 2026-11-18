<?php
namespace App\Console\Commands;
use App\Telegram\VideoEventHandler;
use App\Support\TelegramCredentials;
use Illuminate\Console\Command;
class StudioListen extends Command
{
 protected $signature='rdm:listen'; protected $description='Run MadelineProto channel video listener (CLI daemon)';
 public function handle(): int
 {
    $session=storage_path('app/private/telegram/account.madeline');
    $lock=storage_path('app/private/telegram/listener.lock');
    if(!is_dir(dirname($lock))) mkdir(dirname($lock),0700,true);
    $handle=fopen($lock,'c');
    if(!$handle || !flock($handle,LOCK_EX|LOCK_NB)) {
        $this->error('A Telegram listener is already running, or the lock is unavailable.');
        return self::FAILURE;
    }
    try {
        $this->info('Listening for new channel videos. Press Ctrl+C to stop.');
        VideoEventHandler::startAndLoop($session, TelegramCredentials::appInfo());
    } finally {
        flock($handle,LOCK_UN);
        fclose($handle);
    }
    return self::SUCCESS;
 }
}
