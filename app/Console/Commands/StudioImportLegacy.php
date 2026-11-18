<?php
namespace App\Console\Commands;

use App\Models\{Channel,Video,Render,Publication,Setting,ActivityLog};
use App\Support\RenderSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Facades\Crypt;
use PDO;
use RuntimeException;
use Throwable;

/** One-off non-destructive copy from the previous PHP/SQLite app. Never modifies the source. */
class StudioImportLegacy extends Command
{
    protected $signature='rdm:import-legacy {old-project : absolute path to previous project} {--apply : Actually import after reviewing} {--bot : Also migrate old bot token/settings}';
    protected $description='Preview or import legacy SQLite/videos/renders into an EMPTY Laravel database';

    public function handle(): int
    {
        $old=realpath((string)$this->argument('old-project'));
        if(!$old || !is_file($old.'/storage/db/realdarkmeme.sqlite')){
            $this->error('Legacy storage/db/realdarkmeme.sqlite was not found.');return self::FAILURE;
        }
        if(Channel::count() || Video::count() || Render::count() || Publication::count()){
            $this->error('Import requires an empty new studio database.');return self::FAILURE;
        }
        $dbh=new PDO('sqlite:'.$old.'/storage/db/realdarkmeme.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $rows=fn(string $sql):array=>$dbh->query($sql)->fetchAll();
        $hasPublications=(bool)$dbh->query("SELECT name FROM sqlite_master WHERE type='table' AND name='telegram_publications'")->fetch();
        $channels=$rows('SELECT * FROM channels ORDER BY id');
        $videos=$rows('SELECT * FROM videos ORDER BY id');
        $renders=$rows('SELECT * FROM renders ORDER BY id');
        $publications=$hasPublications?$rows('SELECT * FROM telegram_publications ORDER BY id'):[];
        $this->table(['Legacy entity','Count'],[['channels',count($channels)],['videos',count($videos)],['renders',count($renders)],['publications',count($publications)]]);
        if(!$this->option('apply')){$this->warn('Dry-run only. Add --apply to import. Stop old workers first.');return self::SUCCESS;}

        $disk=Storage::disk('private');
        $copy=function(?string $source,string $target) use($old,$disk):?string {
            if(!$source)return null;
            $src=realpath($source);
            $root=realpath($old.'/storage');
            if(!$src || !$root || !str_starts_with($src,$root.DIRECTORY_SEPARATOR) || !is_file($src))return null;
            $stream=fopen($src,'rb');if(!$stream)throw new RuntimeException('Cannot open '.$src);
            try{$disk->put($target,$stream);}finally{fclose($stream);}
            return $target;
        };
        $cleanTimestamp=fn($s)=>$s?\Carbon\Carbon::parse($s)->format('Y-m-d H:i:s'):null;
        try {
            DB::transaction(function()use($channels,$videos,$renders,$publications,$copy,$cleanTimestamp,$old,$disk,$dbh){
                foreach($channels as $r)DB::table('channels')->insert([
                    'id'=>$r['id'],'peer_id'=>$r['peer_id'],'identifier'=>$r['identifier'],'label'=>$r['label'],'enabled'=>$r['enabled'],
                    'created_at'=>$cleanTimestamp($r['created_at']), 'updated_at'=>$cleanTimestamp($r['created_at']),
                ]);
                foreach($videos as $r){
                    $id=(int)$r['id'];$ext=strtolower(pathinfo((string)$r['source_path'],PATHINFO_EXTENSION));
                    if(!in_array($ext,['mp4','mov','webm','mkv','avi','m4v'],true))$ext='mp4';
                    $path=$copy($r['source_path'],'inputs/'.$id.'.'.$ext);
                    $frame=$copy($r['first_frame_path'],'frames/'.$id.'.png');
                    $status=in_array($r['status'],['downloading','queued','processing'],true)?'failed':$r['status'];
                    if(!$path)$status='failed';
                    DB::table('videos')->insert([
                        'id'=>$id,'channel_id'=>$r['channel_id'],'telegram_message_id'=>$r['telegram_message_id'],
                        'source_path'=>$path,'source_size'=>$r['source_size']??0,'source_date'=>$cleanTimestamp($r['source_date']),
                        'first_frame_path'=>$frame,'ocr_text'=>$r['ocr_text'],'title'=>$r['title'],'caption_body'=>$r['caption_body'],
                        'caption'=>$r['caption'],'overrides'=>$r['overrides_json']?:'{}','status'=>$status,
                        'progress'=>$status==='ready'?100:0,'error'=>$status==='failed'?($r['error']?:'Imported from legacy; review or retry manually.'):null,
                        'current_render_id'=>null,'created_at'=>$cleanTimestamp($r['created_at']),'updated_at'=>$cleanTimestamp($r['updated_at']),
                    ]);
                }
                foreach($renders as $r){
                    $id=(int)$r['video_id'];$ver=(int)$r['version'];
                    $output=$copy($r['output_path'],'outputs/'.$id.'/v'.$ver.'.mp4');
                    $poster=$copy($r['poster_path'],'outputs/'.$id.'/v'.$ver.'.jpg');
                    $status=$r['status']==='ready'&&$output?'ready':'failed';
                    DB::table('renders')->insert([
                        'id'=>$r['id'],'video_id'=>$id,'version'=>$ver,'status'=>$status,
                        'output_path'=>$output,'poster_path'=>$poster,'title'=>$r['title'],'caption'=>$r['caption'],
                        'settings_snapshot'=>$r['settings_json']?:'{}','error'=>$status==='failed'?($r['error']?:'Legacy output file missing'):null,
                        'completed_at'=>$cleanTimestamp($r['completed_at']),
                        'created_at'=>$cleanTimestamp($r['created_at']),'updated_at'=>$cleanTimestamp($r['completed_at']??$r['created_at']),
                    ]);
                }
                foreach($videos as $r){
                    $renderId=(int)$r['current_render_id'];
                    if($renderId && DB::table('renders')->where('id',$renderId)->where('video_id',$r['id'])->where('status','ready')->exists())
                       DB::table('videos')->where('id',$r['id'])->update(['current_render_id'=>$renderId]);
                }
                foreach($publications as $r){
                    $status=in_array($r['status'],['sent','failed','uncertain'],true)?$r['status']:'uncertain';
                    DB::table('publications')->insert([
                        'id'=>$r['id'],'video_id'=>$r['video_id'],'render_id'=>$r['render_id'],
                        'destination'=>$r['chat_id'],'status'=>$status,'telegram_message_id'=>$r['message_id'],
                        'attempts'=>$r['attempts']??0,'approved_by'=>null,'started_at'=>$cleanTimestamp($r['started_at']),
                        'sent_at'=>$cleanTimestamp($r['sent_at']),'error'=>$status==='uncertain'?'Legacy publication imported; verify destination before retry.':$r['error'],
                        'created_at'=>$cleanTimestamp($r['created_at']),'updated_at'=>$cleanTimestamp($r['updated_at']),
                    ]);
                }
                if(is_file($old.'/storage/settings.json')){
                    $s=json_decode((string)file_get_contents($old.'/storage/settings.json'),true);
                    if(is_array($s))Setting::store('render_settings',json_encode(array_intersect_key($s,RenderSettings::defaults()),JSON_UNESCAPED_UNICODE));
                }
                $metas=$dbh->query("SELECT value FROM app_meta WHERE key='hashtags'")->fetch();
                if($metas)Setting::store('hashtags',$metas['value']);
                foreach(['thumbnail.png','outro.png'] as $file) {
                    if(is_file($old.'/storage/assets/'.$file))$copy($old.'/storage/assets/'.$file,'assets/'.$file);
                }
                if($this->option('bot') && is_file($old.'/storage/telegram/bot-config.json')){
                    $bot=json_decode((string)file_get_contents($old.'/storage/telegram/bot-config.json'),true);
                    if(is_array($bot)){
                        if(!empty($bot['token']))Setting::store('bot_token',Crypt::encryptString((string)$bot['token']));
                        if(!empty($bot['chat_id']))Setting::store('bot_chat',(string)$bot['chat_id']);
                        if(!empty($bot['api_url']))Setting::store('bot_api_url',(string)$bot['api_url']);
                    }
                }
            });
            ActivityLog::record('import.completed','Legacy content imported (users/sessions deliberately excluded)');
        }catch(Throwable $e){$this->error('Import failed: '.$e->getMessage());return self::FAILURE;}
        $this->info('Imported. Existing old project is untouched. Login to new admin and verify media before cutover.');
        $this->warn('Telegram user session NOT copied; stop old listener and transfer it separately after verifying ownership.');
        return self::SUCCESS;
    }
}
