<?php
declare(strict_types=1);

namespace App\Support;

/** The allowlist uses the trusted client address from Request::ip(), never raw headers. */
final class AdminIpAllowlist
{
    public static function parse(string $raw): array
    {
        $parts=preg_split('/[\r\n,;]+/', $raw) ?: [];
        return array_values(array_unique(array_filter(array_map('trim',$parts),fn($part)=>$part!=='')));
    }

    public static function valid(string $entry): bool
    {
        [$address,$prefix] = array_pad(explode('/',$entry,2),2,null);
        if(filter_var($address,FILTER_VALIDATE_IP)===false) return false;
        if($prefix===null) return true;
        if($prefix==='' || !ctype_digit($prefix)) return false;
        $max=str_contains($address,':')?128:32;
        return (int)$prefix>=1 && (int)$prefix<=$max;
    }

    public static function matches(string $entry, ?string $ip): bool
    {
        if(!$ip || !self::valid($entry) || filter_var($ip,FILTER_VALIDATE_IP)===false) return false;
        [$address,$prefix]=array_pad(explode('/',$entry,2),2,null);
        $a=inet_pton($address);
        $b=inet_pton($ip);
        if($a===false||$b===false||strlen($a)!==strlen($b)) return false;
        if($prefix===null) return hash_equals($a,$b);
        $bits=(int)$prefix;
        $bytes=intdiv($bits,8);
        if(substr($a,0,$bytes)!==substr($b,0,$bytes)) return false;
        $remaining=$bits%8;
        if($remaining===0) return true;
        $mask=(0xff << (8-$remaining)) & 0xff;
        return (ord($a[$bytes]) & $mask)===(ord($b[$bytes]) & $mask);
    }

    public static function allows(array $entries, ?string $ip): bool
    {
        foreach($entries as $entry) if(self::matches($entry,$ip)) return true;
        return false;
    }
}
