<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class ActiveUser {
 public function handle(Request $r,Closure $next){
  if(!$r->user()?->active || ($r->session()->has('password_stamp') && !hash_equals($r->session()->get('password_stamp'),hash('sha256',$r->user()->password)))){auth()->logout();$r->session()->invalidate();$r->session()->regenerateToken();return $r->expectsJson()?response()->json(['message'=>'Your login session expired. Sign in again.'],401):redirect()->route('login');}
  $r->session()->put('password_stamp',hash('sha256',$r->user()->password));return $next($r);
 }
}
