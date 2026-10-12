<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Validation\ValidationException;
class AuthController {
 public function form(){if(Auth::check())return redirect()->route(auth()->user()->role==='staff'?'staff.home':'dashboard');return view('auth.login');}
 public function login(Request $r){
  $r->merge(['email'=>strtolower(trim((string)$r->input('email')))]);$d=$r->validate(['email'=>'required|string|max:255','password'=>'required|string']);
  // Imported SQLite accounts may have mixed-case / padded emails. Resolve the
  // actual stored account, then let Laravel verify its hashed password in DB.
  // Ticket counters sign in with their Counter ID (employee_code) instead of an email.
  $matches=User::whereRaw('LOWER(TRIM(email)) = ?',[$d['email']])->limit(2)->get();
  if($matches->isEmpty())$matches=User::whereRaw('LOWER(TRIM(employee_code)) = ?',[$d['email']])->limit(2)->get();
  if($matches->count()!==1 || !Auth::attempt(['email'=>$matches->first()->email,'password'=>$d['password'],'active'=>true])) throw ValidationException::withMessages(['email'=>'Email or password is incorrect, or the account is disabled.']);
  $r->session()->regenerate();$r->session()->put('password_stamp',hash('sha256',$r->user()->password)); if($r->user()->role==='staff'){ $r->session()->forget('url.intended'); return redirect()->route('staff.home'); } return redirect()->intended(route($r->user()->role==='cashier'?'pos':'dashboard'));
 }
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('login');}
}
