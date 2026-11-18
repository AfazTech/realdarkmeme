@php
$labels=['ready'=>'Ready','queued'=>'Queued','downloading'=>'Downloading','processing'=>'Processing','failed'=>'Failed','sending'=>'Sending','sent'=>'Published','uncertain'=>'Uncertain'];
$css=['ready'=>'success','sent'=>'success','failed'=>'danger','uncertain'=>'warn','processing'=>'info','sending'=>'info','queued'=>'muted','downloading'=>'info'];
@endphp
<span class="status {{ $css[$status]??'muted' }}"><i></i>{{ $labels[$status]??$status }}</span>
