<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Publication,Render};
use App\Services\TelegramAuthState;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class QueueMonitorController extends Controller
{
    private const TABS=['deliveries','jobs','failed','renders','telegram'];
    private const DELIVERY_STATUSES=['queued','sending','sent','failed','uncertain'];

    public function index(Request $request,TelegramAuthState $telegram)
    {
        $tab=(string)$request->query('tab','deliveries');
        if(!in_array($tab,self::TABS,true))$tab='deliveries';
        $status=(string)$request->query('status','all');
        if(!in_array($status,array_merge(['all'],self::DELIVERY_STATUSES),true))$status='all';

        $counts=Publication::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total','status')->all();
        $uncertain=Publication::query()->with(['sender:id,name,type,enabled','video:id,title'])
            ->where('status','uncertain')->latest('id')->limit(8)->get();
        $hasJobs=Schema::hasTable('jobs');
        $hasFailures=Schema::hasTable('failed_jobs');
        $pendingCount=$hasJobs?DB::table('jobs')->count():0;
        $failedCount=$hasFailures?DB::table('failed_jobs')->count():0;
        $queueCounts=$hasJobs?DB::table('jobs')->selectRaw('queue, count(*) as total')
            ->groupBy('queue')->orderBy('queue')->get():collect();

        $deliveries=null;$jobs=null;$failures=null;$renders=null;
        if($tab==='deliveries'){
            $query=Publication::query()->with(['sender:id,name,type,enabled','video:id,title','render:id,version'])
                ->latest('id');
            if($status!=='all')$query->where('status',$status);
            $deliveries=$query->paginate(20)->withQueryString();
        }
        if($tab==='jobs' && $hasJobs){
            $queue=(string)$request->query('queue','all');
            $query=DB::table('jobs')->select(['id','queue','payload','attempts','reserved_at','available_at','created_at'])
                ->orderByDesc('id');
            if($queue!=='all' && in_array($queue,['telegram','render','publish','default'],true))
                $query->where('queue',$queue);
            $jobs=$query->paginate(20)->withQueryString();
            $jobs->getCollection()->transform(function($job){
                $payload=json_decode((string)$job->payload,true);
                $name=is_array($payload)?(string)($payload['displayName']??''):'';
                // Never expose the serialized or encrypted payload: may contain login credentials.
                $job->display_name=str_starts_with($name,'App\\Jobs\\')
                    ?class_basename($name):'Queued job';
                unset($job->payload);
                return $job;
            });
        }
        if($tab==='failed' && $hasFailures){
            $failures=DB::table('failed_jobs')
                ->select(['id','uuid','connection','queue','exception','failed_at'])
                ->orderByDesc('id')->paginate(20)->withQueryString();
            $failures->getCollection()->transform(function($job){
                $firstLine=explode("\n",(string)$job->exception)[0];
                $job->error_summary=self::safeMessage($firstLine);
                unset($job->exception);
                return $job;
            });
        }
        if($tab==='renders'){
            $renders=Render::query()->with('video:id,title,status')
                ->latest('id')->paginate(20)->withQueryString();
        }
        $telegramState=$tab==='telegram'?$telegram->read():null;

        return view('queues.index',compact(
            'tab','status','counts','uncertain','pendingCount','failedCount','queueCounts',
            'hasJobs','hasFailures','deliveries','jobs','failures','renders','telegramState'
        ));
    }

    private static function safeMessage(string $message): string
    {
        // Exception first line only, without stack traces or secrets. No raw job payloads.
        $message=preg_replace('/\b\d{5,20}:[A-Za-z0-9_-]{20,}\b/u','[REDACTED BOT TOKEN]',$message)??'';
        $message=preg_replace('/\b(?:api[_ -]?hash|password|token|secret|authorization)\s*(?:=>|[:=])\s*(?:"[^"]*"|\'[^\']*\'|\S+)/iu',
            '[REDACTED CREDENTIAL]',$message)??'';
        $message=preg_replace('/\b[A-Fa-f0-9]{32,64}\b/','[REDACTED HASH]',$message)??'';
        return Str::limit(trim($message),350);
    }
}
