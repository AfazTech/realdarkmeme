<?php
namespace App\Support;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
class BotSettings
{
 public static function token(): string
 {
    $v=Setting::fetch('bot_token',''); return $v!==''?Crypt::decryptString($v):'';
 }
 public static function chat(): string { return (string) Setting::fetch('bot_chat',''); }
 public static function apiUrl(): string { return (string) Setting::fetch('bot_api_url',config('realdarkmeme.bot_api_url')); }
 public static function save(array $input): void
 {
    $chat=trim((string)($input['bot_chat']??''));
    if($chat!=='' && !preg_match('/^(?:@[A-Za-z0-9_]{5,32}|-100[0-9]{5,20})$/D',$chat))
       throw ValidationException::withMessages(['bot_chat'=>'Channel ID must be @username or -100...']);
    $api=rtrim(trim((string)($input['bot_api_url']??'https://api.telegram.org/bot')),'/');
    if(!preg_match('~^https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?(?:/[A-Za-z0-9/_-]+)?/bot$~D',$api))
        throw ValidationException::withMessages(['bot_api_url'=>'Bot API URL must use HTTPS and end in /bot.']);
    $host=strtolower((string) parse_url($api,PHP_URL_HOST));
    if(!in_array($host,config('realdarkmeme.bot_api_hosts',[]),true))
      throw ValidationException::withMessages(['bot_api_url'=>'Bot API host is not allowed. Configure allowed hosts in .env.']);
    $token=trim((string)($input['bot_token']??''));
    if($token!=='' && !preg_match('/^[0-9]{5,20}:[A-Za-z0-9_-]{20,200}$/D',$token))
       throw ValidationException::withMessages(['bot_token'=>'Invalid bot token format.']);
    if($token!=='')Setting::store('bot_token',Crypt::encryptString($token));
    Setting::store('bot_chat',$chat); Setting::store('bot_api_url',$api);
 }
 public static function assertReady(): void
 {
    if(self::token()==='' || self::chat()==='')throw ValidationException::withMessages(['bot_chat'=>'Configure the publishing bot and destination channel first.']);
 }
}
