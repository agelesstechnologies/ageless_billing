<?php
namespace App\Http\Controllers;
use App\Models\{ParkPass,Redemption};
use App\Services\RedemptionService;
use Illuminate\Http\Request;
class MachineApiController {
 public function config(Request $r){$m=$r->attributes->get('machine');$ride=$m->ride;return response()->json(['ok'=>true,'api_version'=>2,'machine_id'=>$m->id,'name'=>$m->name,'serial'=>$m->serial,'enabled'=>$m->enabled&&$ride->active&&!$ride->trashed(),'ride'=>['id'=>$ride->id,'name'=>$ride->name,'code'=>$ride->code,'points_per_play'=>$ride->points_per_play],'points_per_play'=>$ride->points_per_play,'pulse_ms'=>$ride->pulse_ms,'cooldown_seconds'=>$ride->cooldown_seconds,'pending'=>Redemption::where('machine_id',$m->id)->whereIn('status',['approved','uncertain'])->exists()]);}
 private function credential(Request $r):string{
  if(!$r->has('credential')&&$r->has('qr_code'))$r->merge(['credential'=>$r->input('qr_code')]);
  $d=$r->validate(['credential'=>['required','string','regex:/^QR-[A-F0-9]{32}$/']]);return $d['credential'];
 }
 public function check(Request $r){$c=$this->credential($r);$m=$r->attributes->get('machine');if(!$m->enabled)return response()->json(['ok'=>false,'code'=>'MACHINE_DISABLED'],403);$p=ParkPass::where('credential',$c)->first();return response()->json(['ok'=>(bool)$p,'usable'=>$p?->usable??false,'balance'=>$p?->balance,'status'=>$p?->status,'expires_at'=>$p?->expires_at?->toIso8601String()],$p?200:404);}
 public function redeem(Request $r,RedemptionService $service){$c=$this->credential($r);$d=$r->validate(['request_id'=>['required','string','regex:/^[A-Za-z0-9_-]{8,80}$/'],'source'=>'nullable|in:qr']);[$body,$status]=$service->redeem($r->attributes->get('machine'),$c,$d['request_id']);return response()->json($body,$status);}
 public function status(Request $r,string $requestId,RedemptionService $service){abort_unless(preg_match('/^[A-Za-z0-9_-]{8,80}$/',$requestId),422);[$b,$s]=$service->status($r->attributes->get('machine'),$requestId);return response()->json($b,$s);}
 public function ack(Request $r,string $requestId,RedemptionService $service){abort_unless(preg_match('/^[A-Za-z0-9_-]{8,80}$/',$requestId),422);$d=$r->validate(['outcome'=>'required|in:pulsed,uncertain']);[$b,$s]=$service->acknowledge($r->attributes->get('machine'),$requestId,$d['outcome']);return response()->json($b,$s);}
}
