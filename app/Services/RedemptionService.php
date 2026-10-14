<?php
namespace App\Services;
use App\Domain\RedemptionPolicy as Policy;
use App\Models\{Machine,ParkPass,Redemption,PointEntry,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class RedemptionService {
 public function redeem(Machine $reader,string $credential,string $requestId):array {
  return DB::transaction(function()use($reader,$credential,$requestId){
   $machine=Machine::whereKey($reader->id)->lockForUpdate()->firstOrFail();$ride=$machine->ride;
   if($old=Redemption::where('machine_id',$machine->id)->where('request_id',$requestId)->first()){
    if(!hash_equals($old->credential_hash,hash('sha256',$credential))) return [['ok'=>false,'success'=>false,'terminal'=>true,'code'=>'REQUEST_CONFLICT','message'=>'Use a fresh request_id for a different QR.'],409];
    return $this->response($old,true,$machine);
   }
   $pass=ParkPass::where('credential',$credential)->lockForUpdate()->first();
   $pending=Redemption::where('machine_id',$machine->id)->whereIn('status',['approved','uncertain'])->exists();
   $cooldown=$pass && Redemption::where('park_pass_id',$pass->id)->where('ride_id',$ride->id)->whereIn('status',['approved','completed','uncertain'])->where('authorized_at','>',now()->subSeconds($ride->cooldown_seconds))->exists();
   $code=Policy::deny($machine->enabled,$ride->active&&!$ride->trashed(),(bool)$pass,$pass?->status??'', $pass?->expires_at?->timestamp??0,now()->timestamp,(int)($pass?->balance??0),(int)$ride->points_per_play,$pending,$cooldown);
   $tx=Redemption::create(['machine_id'=>$machine->id,'request_id'=>$requestId,'credential_hash'=>hash('sha256',$credential),'park_pass_id'=>$pass?->id,'ride_id'=>$ride->id,'ride_name'=>$ride->name,'status'=>$code?'denied':'approved','code'=>$code??'APPROVED','points'=>$code?0:$ride->points_per_play,'balance_after'=>$pass?->balance,'pulse_ms'=>$code?0:$ride->pulse_ms,'authorized_at'=>$code?null:now()]);
   if(!$code){
    $pass->update(['balance'=>$pass->balance-$ride->points_per_play]);
    $tx->update(['balance_after'=>$pass->balance]);
    PointEntry::create(['park_pass_id'=>$pass->id,'delta'=>-$ride->points_per_play,'balance_after'=>$pass->balance,'kind'=>'redeem','redemption_id'=>$tx->id,'note'=>$ride->name]);
   }
   return $this->response($tx,false,$machine);
  },3);
 }
 public function status(Machine $reader,string $requestId):array {
  return DB::transaction(function()use($reader,$requestId){
   $machine=Machine::whereKey($reader->id)->lockForUpdate()->firstOrFail();
   $tx=Redemption::where('machine_id',$machine->id)->where('request_id',$requestId)->first();
   return $tx?$this->response($tx,true,$machine):[['ok'=>false,'terminal'=>false,'code'=>'NOT_FOUND','message'=>'Retry the original request with the same request_id.'],404];
  },3);
 }
 private function response(Redemption $tx,bool $replay,Machine $machine):array {
  if($tx->status==='approved' && (!$machine->enabled||!$machine->ride->active||$machine->ride->trashed()||!Policy::mayPulse($tx->status,$tx->authorized_at->timestamp,now()->timestamp)))
   $tx->update(['status'=>'uncertain','code'=>'REVIEW_REQUIRED']);
  $approved=in_array($tx->status,['approved','completed'],true);
  $body=['ok'=>$approved,'success'=>$approved,'terminal'=>true,'status'=>$tx->status,'code'=>$tx->code,'transaction_id'=>$tx->id,'request_id'=>$tx->request_id,'replay'=>$replay,'approved'=>$approved,'allow_pulse'=>$tx->status==='approved','points_deducted'=>$tx->points,'balance'=>$tx->balance_after,'balance_after'=>$tx->balance_after,'pulse_ms'=>$tx->pulse_ms,'approval_ttl_seconds'=>$tx->status==='approved'?max(0,Policy::APPROVAL_SECONDS-(now()->timestamp-$tx->authorized_at->timestamp)):0,'message'=>match($tx->code){'APPROVED'=>'Points deducted. Send one pulse and acknowledge.','COMPLETED'=>'Pulse acknowledged. Do not pulse again.','INSUFFICIENT_POINTS'=>'Not enough points. Please recharge.','PASS_EXPIRED'=>'Pass expired. Visit the billing counter.','PASS_BLOCKED'=>'Pass blocked. Visit the billing counter.','INVALID_QR'=>'QR pass not found.','MACHINE_PENDING'=>'Previous play needs acknowledgement or operator review.','COOLDOWN'=>'Please wait before the next scan.','MACHINE_DISABLED'=>'Reader disabled.','RIDE_DISABLED'=>'Ride unavailable.','REVERSED'=>'Points returned by an administrator. Do not pulse.','REVIEW_REQUIRED'=>'Do not pulse. Operator review required.',default=>$tx->code}];
  $body['api_version']=2;$body['machine_id']=$machine->id;$body['serial']=$machine->serial;
  $body['ride']=['id'=>$tx->ride_id,'name'=>$tx->ride_name,'code'=>$machine->ride->code];
  return [$body,$approved?200:($tx->status==='denied'?422:409)];
 }
 public function acknowledge(Machine $reader,string $requestId,string $outcome):array {
  return DB::transaction(function()use($reader,$requestId,$outcome){
   $machine=Machine::whereKey($reader->id)->lockForUpdate()->firstOrFail();
   $tx=Redemption::where('machine_id',$machine->id)->where('request_id',$requestId)->lockForUpdate()->firstOrFail();
   if(in_array($tx->status,['approved','uncertain'],true)){
    $tx->update(['status'=>$outcome==='pulsed'?'completed':'uncertain','code'=>$outcome==='pulsed'?'COMPLETED':'REVIEW_REQUIRED','acknowledged_at'=>now()]);
   }
   return $this->response($tx,true,$machine);
  },3);
 }
 public function resolve(Redemption $input,string $action,string $note,User $user):void {
  DB::transaction(function()use($input,$action,$note,$user){
   $machine=Machine::whereKey($input->machine_id)->lockForUpdate()->firstOrFail();
   $tx=Redemption::whereKey($input->id)->lockForUpdate()->firstOrFail();
   if(!in_array($tx->status,['approved','uncertain'],true)) throw ValidationException::withMessages(['resolution'=>'This transaction is already resolved.']);
   if($machine->enabled||$tx->authorized_at->gt(now()->subSeconds(Policy::APPROVAL_SECONDS))) throw ValidationException::withMessages(['resolution'=>'Disable the reader, power it off, and wait until the 30-second approval window has ended before resolving.']);
   if($action==='refund'){
    $pass=ParkPass::whereKey($tx->park_pass_id)->lockForUpdate()->firstOrFail();
    $pass->update(['balance'=>$pass->balance+$tx->points]);
    PointEntry::create(['park_pass_id'=>$pass->id,'delta'=>$tx->points,'balance_after'=>$pass->balance,'kind'=>'ride_refund','redemption_id'=>$tx->id,'user_id'=>$user->id,'note'=>$note]);
   }
   $tx->update(['status'=>$action==='refund'?'reversed':'completed','code'=>$action==='refund'?'REVERSED':'COMPLETED','resolution_note'=>$note,'resolved_by'=>$user->id,'acknowledged_at'=>now()]);

  },3);
 }
}
