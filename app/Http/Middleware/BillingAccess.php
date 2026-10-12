<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class BillingAccess {
 public function handle(Request $request,Closure $next){abort_unless($request->user()?->canBill(),403,'This account has staff-portal access only.');return $next($request);}
}
