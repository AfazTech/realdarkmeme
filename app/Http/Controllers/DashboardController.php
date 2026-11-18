<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use App\Models\{Video,Channel,Publication,ActivityLog};
class DashboardController extends Controller
{
 public function index(){
   return view('dashboard',[
      'total'=>Video::count(),'ready'=>Video::where('status','ready')->count(),
      'processing'=>Video::whereIn('status',['downloading','queued','processing'])->count(),
      'published'=>Publication::where('status','sent')->count(),
      'channels'=>Channel::where('enabled',true)->count(),
      'videos'=>Video::with(['channel','currentRender.publication'])->latest()->limit(8)->get(),
      'activities'=>ActivityLog::latest()->limit(6)->get(),
   ]);
 }
}
