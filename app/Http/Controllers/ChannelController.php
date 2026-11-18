<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\{Channel,ActivityLog};
use Illuminate\Validation\ValidationException;
class ChannelController extends Controller
{
 public function index(){return view('channels.index',['channels'=>Channel::withCount('videos')->latest()->get()]);}
 public function store(Request $r){
    $v=$r->validate(['peer_id'=>'required|string|max:80','label'=>'required|string|max:120']);
    $v['peer_id']=trim($v['peer_id']);
    if(!preg_match('/^-100[0-9]{5,20}$/D',$v['peer_id']))throw ValidationException::withMessages(['peer_id'=>'Numeric channel peer IDs must start with -100.']);
    if(Channel::where('peer_id',$v['peer_id'])->exists())throw ValidationException::withMessages(['peer_id'=>'This channel is already configured.']);
    // Legacy schema requires identifier; the numeric peer ID is enough for new sources.
    $v['identifier']=$v['peer_id'];
    Channel::create($v+['enabled'=>true]);ActivityLog::record('channel.add',$v['peer_id'],auth()->id());
    return back()->with('ok','Source channel added.');
 }
 public function update(Request $r,Channel $channel){
    $v=$r->validate(['label'=>'required|string|max:120','enabled'=>'sometimes|boolean']);
    $channel->update(['label'=>$v['label'],'enabled'=>$r->boolean('enabled')]);
    ActivityLog::record('channel.update',$channel->peer_id,auth()->id());
    return back()->with('ok','Channel settings saved.');
 }
 public function destroy(Channel $channel){
    if($channel->videos()->exists())return back()->withErrors(['channel'=>'This channel has videos. Disable it to preserve the archive.']);
    $channel->delete();return back()->with('ok','Channel deleted.');
 }
}
