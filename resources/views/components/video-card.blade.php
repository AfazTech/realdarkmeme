<article class="video-card">
 <a href="{{ route('videos.show',$video) }}" class="video-cover">
  @if($video->currentRender && $video->currentRender->poster_path)
   <img loading="lazy" src="{{ route('videos.file',[$video,'poster']) }}" alt="Video cover {{ $video->id }}">
  @elseif($video->first_frame_path)
   <span class="cover-empty"><svg class="ico"><use href="#i-play"/></svg></span>
  @else
   <span class="cover-empty"><span>RDM</span></span>
  @endif
  <span class="vid-no">#{{ str_pad($video->id,4,'0',STR_PAD_LEFT) }}</span>
 </a>
 <div class="video-card-body"><div class="video-card-head">@include('components.status',['status'=>$video->status])<span class="mono dim">{{ $video->created_at?->format('m/d') }}</span></div>
 <a href="{{ route('videos.show',$video) }}" class="video-title">{{ $video->title ?: ($video->ocr_text ? mb_strimwidth($video->ocr_text,0,68,'…') : 'Untitled video') }}</a>
 <div class="video-meta"><span>{{ $video->channel?->label }}</span><span>{{ $video->currentRender ? 'V'.$video->currentRender->version : '—' }}</span></div>
 </div>
</article>
