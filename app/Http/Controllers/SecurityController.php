<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{ActivityLog,Setting};
use App\Support\AdminIpAllowlist;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        $raw=(string) Setting::fetch('admin_ip_allowlist','');
        return view('settings.security',[
            'enabled'=>$raw!=='',
            'allowlist'=>$raw,
            'currentIp'=>$request->ip(),
        ]);
    }

    public function saveAllowlist(Request $request)
    {
        $validated=$request->validate([
            'enabled'=>'sometimes|boolean',
            'ips'=>'nullable|string|max:6000',
            'current_password'=>'required|string',
        ]);
        if(!Hash::check($validated['current_password'],$request->user()->password)) {
            throw ValidationException::withMessages(['current_password'=>'Current password is incorrect.']);
        }

        $enabled=$request->boolean('enabled');
        $entries=AdminIpAllowlist::parse((string)($validated['ips']??''));
        if(count($entries)>50) throw ValidationException::withMessages(['ips'=>'No more than 50 entries are allowed.']);
        foreach($entries as $entry) {
            if(!AdminIpAllowlist::valid($entry)) {
                throw ValidationException::withMessages(['ips'=>'Invalid IPv4, IPv6 or CIDR entry: '.$entry]);
            }
        }
        if($enabled && (!$entries || !AdminIpAllowlist::allows($entries,$request->ip()))) {
            throw ValidationException::withMessages([
                'ips'=>'Your current detected IP address must be permitted before enabling the restriction.',
            ]);
        }
        Setting::store('admin_ip_allowlist',$enabled?implode("\n",$entries):'');
        ActivityLog::record('security.ip_allowlist',$enabled?'Enabled IP allowlist':'Disabled IP allowlist',$request->user()->id);
        return redirect()->route('settings.security')->with('ok',$enabled?'IP restrictions enabled.':'IP restrictions disabled.');
    }
}
