<?php
namespace App\Http\Controllers;
use App\Models\{Machine,Ride,Redemption};
use App\Services\{FirmwareBundle};
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule,ValidationException};
class MachineController {
 public function index(){return view('machines.index',['machines'=>Machine::with('ride')->get(),'serverUrl'=>FirmwareBundle::suggestedUrl(url('/'))]);}
 public function create(){return view('machines.form',['machine'=>new Machine(['enabled'=>true]),'rides'=>Ride::where('active',true)->get()]);}
 public function edit(Machine $machine){return view('machines.form',['machine'=>$machine,'rides'=>Ride::withTrashed()->get()]);}
 private function data(Request $r,?Machine $m=null):array{
  $d=$r->validate(['name'=>'required|string|max:100','serial'=>['required','regex:/^[A-Za-z0-9_-]{1,64}$/',Rule::unique('machines')->ignore($m?->id)],'ride_id'=>['required',Rule::exists('rides','id')],'enabled'=>'nullable|boolean']);$ride=Ride::withTrashed()->find($d['ride_id']);if($ride->trashed()&&(!$m||$m->ride_id!=$ride->id))throw ValidationException::withMessages(['ride_id'=>'Assign a non-deleted ride.']);$d['enabled']=$r->boolean('enabled');return $d;
 }
 public function store(Request $r){$d=$this->data($r);$token=bin2hex(random_bytes(32));$m=\Illuminate\Support\Facades\DB::transaction(function()use($d,$token){$m=Machine::create($d+['token_hash'=>hash('sha256',$token)]);return $m;});return redirect()->route('machines.index')->with('machine_token',$token)->with('machine_token_id',$m->id)->with('success','Reader created. Save the token now; it is shown only once.');}
 public function update(Request $r,Machine $machine){
  $d=$this->data($r,$machine);
  \Illuminate\Support\Facades\DB::transaction(function()use($machine,$d){
   $m=Machine::whereKey($machine->id)->lockForUpdate()->firstOrFail();
   if($d['ride_id']!=$m->ride_id && Redemption::where('machine_id',$m->id)->whereIn('status',['approved','uncertain'])->exists()) throw ValidationException::withMessages(['ride_id'=>'Resolve pending transactions before assigning another ride.']);
   $m->update($d);
  });
  return redirect()->route('machines.index')->with('success','Reader updated.');
 }
 public function rotate(Machine $machine){
  $token=bin2hex(random_bytes(32));
  \Illuminate\Support\Facades\DB::transaction(function()use($machine,$token){
   $m=Machine::whereKey($machine->id)->lockForUpdate()->firstOrFail();
   if(Redemption::where('machine_id',$m->id)->whereIn('status',['approved','uncertain'])->exists()) throw ValidationException::withMessages(['token'=>'Resolve pending plays before rotating the token.']);
   $m->update(['token_hash'=>hash('sha256',$token)]);
  });
  return redirect()->route('machines.index')->with('machine_token',$token)->with('machine_token_id',$machine->id)->with('success','Token replaced. Update the ESP32 configuration.');
 }
 public function cameraConfig(Request $request,Machine $machine) {
  $data=$request->validate(['machine_token'=>'required|regex:/^[a-f0-9]{64}$/','server_url'=>'required|url:http,https|max:255']);
  if(!hash_equals($machine->token_hash,hash('sha256',$data['machine_token'])))throw ValidationException::withMessages(['machine_token'=>'The token does not match this reader. Use its original token or explicitly rotate after resolving pending plays.']);
  $url=FirmwareBundle::serverUrl($data['server_url']);
  return response(FirmwareBundle::config($machine,$data['machine_token'],$url),200,['Content-Type'=>'text/plain; charset=UTF-8','Content-Disposition'=>'attachment; filename="Config.h"','Cache-Control'=>'private, no-store']);
 }
}
