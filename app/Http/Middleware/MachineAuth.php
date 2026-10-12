<?php
namespace App\Http\Middleware;
use App\Models\Machine;
use Closure;
use Illuminate\Http\Request;
class MachineAuth {
 public function handle(Request $request, Closure $next) {
  $token=$request->bearerToken();
  $machine=$token?Machine::where('token_hash',hash('sha256',$token))->first():null;
  if(!$machine) return response()->json(['ok'=>false,'code'=>'UNAUTHENTICATED','message'=>'Invalid machine token.'],401);
  // Disabled readers may still acknowledge an already-delivered pulse.
  if(!$machine->last_seen_at || $machine->last_seen_at->lt(now()->subSeconds(20))) $machine->update(['last_seen_at'=>now()]);
  $request->attributes->set('machine',$machine);
  $response=$next($request);
  $response->headers->set('Cache-Control','private, no-store');
  $response->headers->set('Pragma','no-cache');
  return $response;
 }
}
