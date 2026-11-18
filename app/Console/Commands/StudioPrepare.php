<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
class StudioPrepare extends Command
{
 protected $signature='rdm:prepare'; protected $description='Copy default cover/outro into private storage (without overwriting existing assets)';
 public function handle(): int
 {
    $disk=Storage::disk('private');
    foreach(['inputs','frames','outputs','jobs','telegram','assets'] as $folder) $disk->makeDirectory($folder);
    foreach(['thumbnail.png','outro.png'] as $file) {
       if(!$disk->exists('assets/'.$file))$disk->put('assets/'.$file,file_get_contents(base_path('assets/'.$file)));
    }
    $this->info('Private assets checked.');return self::SUCCESS;
 }
}
