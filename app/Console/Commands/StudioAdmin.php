<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class StudioAdmin extends Command
{
 protected $signature='rdm:admin {username=admin}';
 protected $description='Create/update studio admin credentials interactively';
 public function handle(): int
 {
   $username=(string)$this->argument('username');
   if(!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/',$username)){$this->error('Username must be 3-40 characters (a-z, 0-9, _, ., -).');return self::FAILURE;}
   $password=(string)$this->secret('Admin password (any nonempty length)');
   if($password===''){$this->error('Password cannot be empty.');return self::FAILURE;}
   User::query()->updateOrCreate(['username'=>$username],['password'=>Hash::make($password)]);
   $this->info('Admin account saved.');return self::SUCCESS;
 }
}
