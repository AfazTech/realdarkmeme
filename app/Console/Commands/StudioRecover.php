<?php
namespace App\Console\Commands;
use App\Models\Publication;
use Illuminate\Console\Command;
class StudioRecover extends Command
{
 protected $signature='rdm:recover-publications'; protected $description='Mark interrupted Telegram uploads as uncertain; never resend automatically';
 public function handle(): int
 {
    $n=Publication::query()->where('status','sending')->where('started_at','<',now()->subMinutes(65))
       ->update(['status'=>'uncertain','error'=>'Publisher stopped during upload. Check channel before retry.']);
    $this->info('Marked '.$n.' interrupted uploads as uncertain.');return self::SUCCESS;
 }
}
