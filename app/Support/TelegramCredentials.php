<?php
namespace App\Support;

use App\Models\Setting;
use danog\MadelineProto\Settings\AppInfo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class TelegramCredentials
{
    public static function apiId(): ?int
    {
        $v=Setting::fetch('telegram_api_id','');
        return preg_match('/^[0-9]{4,12}$/D',(string)$v) ? (int)$v : null;
    }

    public static function hasHash(): bool
    {
        return Setting::fetch('telegram_api_hash','') !== '';
    }

    public static function configured(): bool
    {
        return self::apiId() !== null && self::hasHash();
    }

    public static function appInfo(): ?AppInfo
    {
        if (!self::configured()) return null;
        return (new AppInfo())
            ->setApiId(self::apiId())
            ->setApiHash(Crypt::decryptString(Setting::fetch('telegram_api_hash')));
    }

    public static function save(int $id, ?string $hash): void
    {
        $current = self::apiId();
        $hash=trim((string)$hash);
        if ($hash === '' && ($current !== $id || !self::hasHash())) {
            throw ValidationException::withMessages(['api_hash'=>'Enter the API hash when configuring new credentials or changing the API ID.']);
        }
        Setting::store('telegram_api_id',(string)$id);
        if ($hash !== '') Setting::store('telegram_api_hash',Crypt::encryptString($hash));
    }
}
