<?php
namespace App\Http\Controllers;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
class AuthController extends Controller
{
 public function show(){return view('auth.login');}
 public function login(Request $r){
    $data=$r->validate(['username'=>'required|string','password'=>'required|string']);
    if(Auth::attempt(['username'=>$data['username'],'password'=>$data['password']])){
       $r->session()->regenerate(); ActivityLog::record('auth.login','Admin login',Auth::id());
       return redirect()->intended(route('dashboard'));
    }
    return back()->withErrors(['username'=>'Incorrect username or password.'])->onlyInput('username');
 }
 public function logout(Request $r){ Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('login'); }
}
