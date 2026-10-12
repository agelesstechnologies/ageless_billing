<?php
namespace App\Http\Controllers;

use App\Models\{Ride,Machine};
use App\Services\{Audit,FirmwareBundle};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};

class RideSetupController {
 public function form() {
  $rows=Ride::orderBy('id')->limit(25)->get()->map(fn($r)=>$r->only(['id','name','code','points_per_play','pulse_ms','cooldown_seconds','active']))->all();
  $codes=Ride::withTrashed()->pluck('code')->map(fn($c)=>strtoupper($c))->all();$next=1;
  while(count($rows)<25) {
   do{$code='RIDE-'.sprintf('%02d',$next++);}while(in_array($code,$codes,true));$codes[]=$code;
   $rows[]=['id'=>null,'name'=>'','code'=>$code,'points_per_play'=>'','pulse_ms'=>250,'cooldown_seconds'=>5,'active'=>false];
  }
  return view('rides.setup',['rows'=>$rows,'serverUrl'=>FirmwareBundle::suggestedUrl(url('/'))]);
 }
 public function save(Request $request) {
  $data=$request->validate([
   'server_url'=>'required|url:http,https|max:255','create_readers'=>'nullable|boolean',
   'rides'=>'required|array|size:25','rides.*'=>'required|array',
   'rides.*.id'=>['nullable','integer','distinct',Rule::exists('rides','id')->whereNull('deleted_at')],
   'rides.*.name'=>'required|string|max:100','rides.*.code'=>'required|regex:/^[A-Za-z0-9_-]{1,32}$/',
   'rides.*.points_per_play'=>'required|integer|min:1|max:1000000','rides.*.pulse_ms'=>'required|integer|min:50|max:2000',
   'rides.*.cooldown_seconds'=>'required|integer|min:2|max:300','rides.*.active'=>'nullable|boolean',
  ]);
  $url=FirmwareBundle::serverUrl($data['server_url']);$selected=[];
  foreach($data['rides'] as $i=>&$row) {
   $row['code']=strtoupper($row['code']);
   if(isset($selected[$row['code']]))throw ValidationException::withMessages(["rides.$i.code"=>'Each ride code must be unique.']);
   $selected[$row['code']]=true;
  }unset($row);
  // Generate the complete one-time download before commit, so a missing
  // firmware template cannot leave readers created without their credentials.
  $archive=DB::transaction(function()use($data,$request,$url){
   $existing=Ride::withTrashed()->lockForUpdate()->get();$devices=[];$retained=[];
   foreach($data['rides'] as $i=>$row) {
    foreach($existing as $other)if(strtoupper($other->code)===$row['code'] && $other->id!=($row['id']??null))throw ValidationException::withMessages(["rides.$i.code"=>'That code already belongs to another ride.']);
   }
   foreach($data['rides'] as $row) {
    $values=['name'=>$row['name'],'code'=>$row['code'],'points_per_play'=>$row['points_per_play'],'pulse_ms'=>$row['pulse_ms'],'cooldown_seconds'=>$row['cooldown_seconds'],'active'=>(bool)($row['active']??false)];
    $ride=empty($row['id'])?Ride::create($values):Ride::findOrFail($row['id']);
    if(!empty($row['id']))$ride->update($values);
    Audit::record(empty($row['id'])?'ride.created':'ride.updated','ride',$ride->id,['source'=>'25_ride_setup','points_per_play'=>$ride->points_per_play]);
    if(!$request->boolean('create_readers'))continue;
    $readers=Machine::where('ride_id',$ride->id)->get();
    if($readers->isNotEmpty()){$retained=array_merge($retained,$readers->pluck('serial')->all());continue;}
    $serial='ESP-'.$ride->code;
    if(Machine::where('serial',$serial)->exists())throw ValidationException::withMessages(['create_readers'=>'Reader serial '.$serial.' already exists for another ride. Give this ride a different code.']);
    $token=bin2hex(random_bytes(32));
    $machine=Machine::create(['name'=>mb_substr($ride->name.' QR camera',0,100),'serial'=>$serial,'ride_id'=>$ride->id,'enabled'=>false,'token_hash'=>hash('sha256',$token)]);
    Audit::record('machine.created','machine',$machine->id,['source'=>'25_ride_setup']);
    $devices[]=['machine'=>$machine,'token'=>$token];
   }
   return FirmwareBundle::pack($devices,$url,$retained);
  });
  return response($archive,200,['Content-Type'=>'application/zip','Content-Disposition'=>'attachment; filename="Ageless_25_Ride_Camera_Config.zip"','Cache-Control'=>'private, no-store']);
 }
}
