<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{ActivityLog,Setting};
use Illuminate\Console\Command;

/** Server-console recovery path if an administrator changes networks. */
class StudioIpUnlock extends Command
{
    protected $signature='rdm:ip-unlock';
    protected $description='Disable admin IP restrictions from the server console';

    public function handle(): int
    {
        if(!$this->confirm('Disable the admin IP allowlist on this server?',false)) return self::FAILURE;
        Setting::store('admin_ip_allowlist','');
        ActivityLog::record('security.ip_allowlist.recovery','IP restrictions disabled from console');
        $this->info('IP restrictions are now disabled.');
        return self::SUCCESS;
    }
}
