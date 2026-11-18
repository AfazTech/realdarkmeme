<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage,Hash};
use Illuminate\Validation\ValidationException;
use App\Models\{Setting,ActivityLog};
use App\Support\{RenderSettings,BotSettings};
use Neili\{Client,Settings};
use Throwable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
class SettingsController extends Controller
{
 public function index(){
    $disk=Storage::disk('private');
    $assets=[];
    foreach(['thumbnail'=>'thumbnail.png','outro'=>'outro.png'] as $key=>$name){
        $path='assets/'.$name;
        $exists=$disk->exists($path);
        $assets[$key]=[
            'exists'=>$exists,
            'size'=>$exists ? $disk->size($path) : null,
            'updated'=>$exists ? $disk->lastModified($path) : null,
        ];
    }
    return view('settings.index',[
       'schema'=>RenderSettings::schema(),
       'render'=>RenderSettings::current(),
       'mediaAssets'=>$assets,
    ]);
 }
 public function assetPreview(string $kind): BinaryFileResponse
 {
    $files=['thumbnail'=>'thumbnail.png','outro'=>'outro.png'];
    abort_unless(isset($files[$kind]),404);
    $disk=Storage::disk('private');
    $path='assets/'.$files[$kind];
    abort_unless($disk->exists($path),404);
    return response()->file($disk->path($path),[
        'Content-Type'=>'image/png',
        'Cache-Control'=>'private, no-store, max-age=0',
        'X-Content-Type-Options'=>'nosniff',
    ]);
 }
 public function general(){
    return view('settings.general',[
      'hashtags'=>Setting::fetch('hashtags',''), 'bot_chat'=>BotSettings::chat(),
      'bot_api_url'=>BotSettings::apiUrl(), 'token_set'=>BotSettings::token()!=='',
    ]);
 }
 public function hashtags(Request $r){
    $v=$r->validate(['hashtags'=>'nullable|string|max:2000']);
    Setting::store('hashtags',RenderSettings::hashtags((string)($v['hashtags']??'')));
    ActivityLog::record('settings.hashtags','Default hashtags updated',auth()->id());
    return back()->with('ok','Default hashtags saved.');
 }
 public function render(Request $r){
    $values=RenderSettings::validate($r->input('settings',[]));
    Setting::store('render_settings',json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    ActivityLog::record('settings.render','Render configuration updated',auth()->id());
    return back()->with('ok','Studio rendering settings saved.');
 }
 public function bot(Request $r){
    $v=$r->validate(['bot_token'=>'nullable|string|max:220','bot_chat'=>'nullable|string|max:100','bot_api_url'=>'required|string|max:200']);
    BotSettings::save($v);ActivityLog::record('settings.bot','Bot configuration updated (token hidden)',auth()->id());
    return back()->with('ok','Telegram publishing settings saved.');
 }
 public function testBot(){
    BotSettings::assertReady();
    try {
      $settings=(new Settings())->setAccessToken(BotSettings::token())->setApiUrl(BotSettings::apiUrl())->setTimeout(20,8);
      $res=(new Client($settings))->getMe()->await();
      $name=$res['result']['username']??$res['username']??'bot';
      return back()->with('ok','Bot connection verified: @'.$name.' (channel posting permissions are not verified).');
    }catch(Throwable $e){return back()->withErrors(['bot'=>'Bot connection failed. Check the server logs.']);}
 }
 public function assets(Request $r){
    $r->validate(['thumbnail'=>'nullable|image|mimes:png|max:10240','outro'=>'nullable|image|mimes:png|max:10240']);
    $disk=Storage::disk('private');
    foreach(['thumbnail'=>'thumbnail.png','outro'=>'outro.png'] as $key=>$name) {
        if($r->hasFile($key)) {
           $f=$r->file($key); if(!$f->isValid())throw ValidationException::withMessages([$key=>'Invalid uploaded file.']);
           $disk->putFileAs('assets',$f,$name);
        }
    }
    ActivityLog::record('settings.assets','Cover/outro assets updated',auth()->id());
    return back()->with('ok','Studio images updated.');
 }
 public function password(Request $r){
    $v=$r->validate(['current_password'=>'required|string','password'=>'required|string|confirmed']);
    $user=$r->user();if(!Hash::check($v['current_password'],$user->password))throw ValidationException::withMessages(['current_password'=>'The current password is incorrect.']);
    $user->update(['password'=>$v['password']]);$r->session()->regenerate();
    ActivityLog::record('account.password','Password updated',auth()->id());
    return back()->with('ok','Admin password updated.');
 }
}
