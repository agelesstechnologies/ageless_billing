<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class SecurityHeaders {
 public function handle(Request $r, Closure $next) {
  $response=$next($r);
  $response->headers->set('X-Content-Type-Options','nosniff');
  $response->headers->set('X-Frame-Options','DENY');
  $response->headers->set('Referrer-Policy','same-origin');
  $response->headers->set('Cache-Control','no-store, private');
  $response->headers->set('Permissions-Policy','camera=(), microphone=(), geolocation=(self)');
  return $response;
 }
}
