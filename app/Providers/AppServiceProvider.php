<?php
namespace App\Providers;
use App\Models\Setting;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
  \Illuminate\Pagination\Paginator::defaultView('pagination');
  // Two independent buckets: per email+IP (guessing one account) and per IP (spraying many accounts from one address).
  RateLimiter::for('login',fn($r)=>[Limit::perMinute(5)->by(strtolower((string)$r->input('email')).'|'.$r->ip()),Limit::perMinute(20)->by('ip:'.$r->ip())]);
  RateLimiter::for('attendance',fn($r)=>Limit::perMinute(20)->by((string)$r->user()?->id.'|'.$r->ip()));
  // 25 readers may share one public NAT IP; keep each reader's own cap.
  RateLimiter::for('machine',fn($r)=>[Limit::perMinute(3600)->by('ip:'.$r->ip()),Limit::perMinute(120)->by('token:'.hash('sha256',(string)$r->bearerToken()))]);
  View::composer('*',function($view){ $view->with('park',Setting::current()); });
 }
}
