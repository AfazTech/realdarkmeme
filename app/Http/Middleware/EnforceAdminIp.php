<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\AdminIpAllowlist;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAdminIp
{
    public function handle(Request $request,Closure $next): Response
    {
        $raw=(string) Setting::fetch('admin_ip_allowlist','');
        if($raw!=='' && !AdminIpAllowlist::allows(AdminIpAllowlist::parse($raw),$request->ip())) {
            abort(403,'This IP address is not permitted to access the administration panel.');
        }
        return $next($request);
    }
}
