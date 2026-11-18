@extends('layouts.app')
@section('title','Queue Monitor')
@section('content')
<div class="page-intro">
 <div><span class="eyebrow">OPERATIONS / JOBS</span><h1>Queue Monitor <span class="soft-dot">.</span></h1><p>Review publication outcomes, pending workers, failures, render history and Telegram authorization.</p></div>
 <a class="btn btn-subtle" href="{{ request()->fullUrl() }}">Refresh status</a>
</div>
<div class="queue-metrics">
 <a href="{{ route('queues.index',['tab'=>'deliveries','status'=>'uncertain']) }}" class="queue-metric"><span>Uncertain deliveries</span><strong>{{ number_format($counts['uncertain']??0) }}</strong><small>May already be published · review manually</small></a>
 <a href="{{ route('queues.index',['tab'=>'deliveries','status'=>'failed']) }}" class="queue-metric"><span>Failed deliveries</span><strong>{{ number_format($counts['failed']??0) }}</strong><small>Errors from publication attempts</small></a>
 <a href="{{ route('queues.index',['tab'=>'jobs']) }}" class="queue-metric"><span>Pending / claimed jobs</span><strong>{{ number_format($pendingCount) }}</strong><small>Currently present in Laravel jobs table</small></a>
 <a href="{{ route('queues.index',['tab'=>'failed']) }}" class="queue-metric"><span>Failed Laravel jobs</span><strong>{{ number_format($failedCount) }}</strong><small>Entries kept in failed_jobs</small></a>
</div>
@if($uncertain->isNotEmpty())
<section class="surface queue-attention">
 <div class="box-heading"><div><span class="eyebrow">ACTION REQUIRED</span><h3>publish.uncertain · Outcome not confirmed</h3></div><a class="btn btn-subtle" href="{{ route('queues.index',['tab'=>'deliveries','status'=>'uncertain']) }}">All uncertain</a></div>
 <p class="subparagraph">The sender call was attempted, but the app did not receive a definitive result. <strong>The video may already be published.</strong> Check the destination channel before retrying. There is no automatic retry for uncertain deliveries.</p>
 @foreach($uncertain as $publication)
 <div class="queue-attention-item">
  <div><strong>#{{ $publication->id }} · {{ $publication->sender?->name??'Unknown Sender' }}</strong>
   <div class="dim tiny">Video <a href="{{ route('videos.show',$publication->video_id) }}">#{{ $publication->video_id }}</a> · {{ $publication->destination }} · {{ $publication->started_at?->format('Y-m-d H:i:s')??'Not recorded' }}</div>
   <p class="queue-error">{{ $publication->display_error ?: 'The sender did not return a confirmed outcome.' }}</p>
  </div>
  @if($publication->sender?->enabled)
  <form action="{{ route('publications.retry',$publication) }}" method="post" class="queue-retry">@csrf
   <label><input type="checkbox" name="confirm_uncertain" value="1" required> I checked the destination and accept the duplicate-post risk.</label>
   <button class="btn btn-outline" type="submit">Retry delivery</button>
  </form>
  @else<a class="btn btn-subtle" href="{{ route('senders.index') }}">Enable Sender first</a>@endif
 </div>
 @endforeach
</section>
@endif
<nav class="queue-tabs" aria-label="Queue monitor sections">
 @foreach(['deliveries'=>'Publication results','jobs'=>'Pending jobs','failed'=>'Failed jobs','renders'=>'Render history','telegram'=>'Telegram operations'] as $key=>$label)
 <a href="{{ route('queues.index',['tab'=>$key]) }}" class="{{ $tab===$key?'active':'' }}">{{ $label }}</a>
 @endforeach
</nav>

@if($tab==='deliveries')
<section class="surface">
 <div class="box-heading"><div><span class="eyebrow">DELIVERY HISTORY</span><h3>Publication results</h3></div><span class="section-tag">{{ array_sum($counts) }} records</span></div>
 <p class="subparagraph">A completed queue job is removed from the jobs table. Publication records keep sender-specific results and errors after the worker finishes.</p>
 <div class="queue-filters">@foreach(['all'=>'All','queued'=>'Queued','sending'=>'Sending','sent'=>'Published','failed'=>'Failed','uncertain'=>'Uncertain'] as $key=>$label)
  <a class="filter {{ $status===$key?'active':'' }}" href="{{ route('queues.index',['tab'=>'deliveries','status'=>$key]) }}">{{ $label }}@if($key!=='all') ({{ $counts[$key]??0 }})@endif</a>
 @endforeach</div>
 @if($deliveries->count())
 <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>Publication</th><th>Video / Version</th><th>Sender / Destination</th><th>Status</th><th>Result / Problem</th><th>Attempt / Time</th></tr></thead><tbody>
 @foreach($deliveries as $p)<tr>
  <td><strong>#{{ $p->id }}</strong></td>
  <td><a href="{{ route('videos.show',$p->video_id) }}">Video #{{ $p->video_id }}</a><small>V{{ $p->render?->version??'?' }}</small></td>
  <td>{{ $p->sender?->name??'Legacy Sender' }}<small>{{ $p->destination }}</small></td>
  <td>@include('components.status',['status'=>$p->status])</td>
  <td>
   @if($p->status==='sent')<span class="queue-good">Published successfully</span>@if($p->telegram_message_id)<small>Telegram message #{{ $p->telegram_message_id }}</small>@endif
   @elseif($p->status==='uncertain')<b class="queue-warning">Delivery may have succeeded</b><small>{{ $p->display_error?:'No confirmation was received.' }}</small>
   @elseif($p->status==='failed')<b class="queue-error">Delivery failed</b><small>{{ $p->display_error?:'No error details recorded.' }}</small>
   @elseif($p->status==='sending')<span>Sender request started</span>
   @else<span>Waiting for a publish worker</span>@endif
  </td>
  <td>{{ $p->attempts }} attempt(s)<small>{{ $p->sent_at?->format('Y-m-d H:i:s')??$p->started_at?->format('Y-m-d H:i:s')??$p->created_at?->format('Y-m-d H:i:s') }}</small></td>
 </tr>@endforeach
 </tbody></table></div><div class="pagination">{{ $deliveries->links('pagination.simple') }}</div>
 @else<div class="empty-inline">No publication records match this filter.</div>@endif
</section>
@elseif($tab==='jobs')
<section class="surface">
 <div class="box-heading"><div><span class="eyebrow">LARAVEL / DATABASE QUEUE</span><h3>Pending and claimed jobs</h3></div><span class="section-tag">{{ $pendingCount }} jobs</span></div>
 <p class="subparagraph">Only jobs currently present in the database queue appear here. A claimed job may be running or waiting for recovery; job payloads and Telegram login secrets are never shown.</p>
 <div class="queue-filters"><a class="filter {{ request('queue','all')==='all'?'active':'' }}" href="{{ route('queues.index',['tab'=>'jobs']) }}">All</a>
 @foreach(['telegram','render','publish','default'] as $queue)<a class="filter {{ request('queue')===$queue?'active':'' }}" href="{{ route('queues.index',['tab'=>'jobs','queue'=>$queue]) }}">{{ ucfirst($queue) }}</a>@endforeach</div>
 <div class="queue-counts">@foreach($queueCounts as $count)<span>{{ $count->queue }}: <b>{{ $count->total }}</b></span>@endforeach</div>
 @if(!$hasJobs)<div class="empty-inline">The jobs table is not installed. Run the project's database migrations.</div>
 @elseif($jobs->count())
 <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>Job ID</th><th>Type</th><th>Queue</th><th>State</th><th>Attempts</th><th>Created</th></tr></thead><tbody>
 @foreach($jobs as $job)<tr><td>#{{ $job->id }}</td><td>{{ $job->display_name }}</td><td>{{ $job->queue }}</td><td>{{ $job->reserved_at?'Claimed by worker':'Waiting' }}</td><td>{{ $job->attempts }}</td><td>{{ date('Y-m-d H:i:s',$job->created_at) }}<small>Available: {{ date('Y-m-d H:i:s',$job->available_at) }}</small></td></tr>@endforeach
 </tbody></table></div><div class="pagination">{{ $jobs->links('pagination.simple') }}</div>
 @else<div class="empty-inline">No pending jobs in this queue. Completed jobs are removed automatically.</div>@endif
</section>
@elseif($tab==='failed')
<section class="surface">
 <div class="box-heading"><div><span class="eyebrow">WORKER FAILURES</span><h3>Failed Laravel jobs</h3></div><span class="section-tag">{{ $failedCount }} failures</span></div>
 <p class="subparagraph">Shows the first error line without raw payloads, full stack traces, tokens or verification codes. To avoid duplicate posts, generic Laravel job retry is intentionally unavailable here. Retry a failed publication from its video detail after reviewing the destination.</p>
 @if(!$hasFailures)<div class="empty-inline">The failed_jobs table is not available.</div>
 @elseif($failures->count())
 <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>Failure</th><th>Queue</th><th>Problem</th><th>Time</th></tr></thead><tbody>
 @foreach($failures as $failure)<tr><td>#{{ $failure->id }}<small>{{ \Illuminate\Support\Str::limit($failure->uuid,18) }}</small></td><td>{{ $failure->queue }}</td><td class="queue-error">{{ $failure->error_summary ?: 'No error details recorded.' }}</td><td>{{ $failure->failed_at }}</td></tr>@endforeach
 </tbody></table></div><div class="pagination">{{ $failures->links('pagination.simple') }}</div>
 @else<div class="empty-inline">No Laravel worker failures have been recorded.</div>@endif
</section>
@elseif($tab==='renders')
<section class="surface">
 <div class="box-heading"><div><span class="eyebrow">VIDEO PRODUCTION</span><h3>Render history</h3></div></div>
 <p class="subparagraph">Render records retain success/failure states after the render queue job is removed. Open a video to see its full version history and retry controls.</p>
 @if($renders->count())
 <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>Render</th><th>Video</th><th>Status</th><th>Result / Problem</th><th>Completed</th></tr></thead><tbody>
 @foreach($renders as $render)<tr><td><strong>#{{ $render->id }}</strong><small>Version {{ $render->version }}</small></td>
 <td><a href="{{ route('videos.show',$render->video_id) }}">Video #{{ $render->video_id }}</a><small>{{ \Illuminate\Support\Str::limit($render->video?->title??'',45) }}</small></td>
 <td>@include('components.status',['status'=>$render->status])</td>
 <td>@if($render->status==='ready')<span class="queue-good">MP4 ready</span>@elseif($render->status==='failed')<span class="queue-error">{{ $render->display_error?:'Rendering failed without recorded details.' }}</span>@else{{ ucfirst($render->status) }}@endif</td>
 <td>{{ $render->completed_at?->format('Y-m-d H:i:s')??'Not finished' }}</td></tr>@endforeach
 </tbody></table></div><div class="pagination">{{ $renders->links('pagination.simple') }}</div>
 @else<div class="empty-inline">No render history yet.</div>@endif
</section>
@else
<section class="surface">
 <div class="box-heading"><div><span class="eyebrow">TELEGRAM / AUTH QUEUE</span><h3>Account operation status</h3></div><a class="btn btn-subtle" href="{{ route('telegram.index') }}">Telegram Account</a></div>
 <p class="subparagraph">This is the most recent authorization operation saved locally. It does not connect to MadelineProto or expose login codes and passwords.</p>
 <div class="queue-state-grid">
 <div><small>Queue job</small><strong>{{ ucfirst($telegramState['job_state']) }}</strong></div>
 <div><small>Account state</small><strong>{{ str_replace('_',' ',ucfirst($telegramState['state'])) }}</strong></div>
 <div><small>Operation</small><strong>{{ $telegramState['action']??'None' }}</strong></div>
 <div><small>Session exists</small><strong>{{ $telegramState['exists']?'Yes':'No' }}</strong></div>
 </div>
 @if($telegramState['error'])<div class="inline-error">{{ $telegramState['error'] }}</div>
 @elseif($telegramState['message'])<p class="helper-note">{{ $telegramState['message'] }}</p>@endif
</section>
@endif
@endsection
