<?php
namespace App\Http\Controllers;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class RideController {
 public function index(){return view('rides.index',['rides'=>Ride::withCount('machines')->get()]);}
 public function create(){return view('rides.form',['ride'=>new Ride(['active'=>true,'points_per_play'=>100,'pulse_ms'=>250,'cooldown_seconds'=>5])]);}
 public function edit(Ride $ride){return view('rides.form',compact('ride'));}
 private function data(Request $r,?Ride $ride=null):array{
  $d=$r->validate(['name'=>'required|string|max:100','code'=>['required','regex:/^[A-Za-z0-9_-]{1,32}$/',Rule::unique('rides')->ignore($ride?->id)],'description'=>'nullable|string|max:1000','points_per_play'=>'required|integer|min:1|max:1000000','pulse_ms'=>'required|integer|min:50|max:2000','cooldown_seconds'=>'required|integer|min:2|max:300','active'=>'nullable|boolean']);$d['active']=$r->boolean('active');return $d;
 }
 public function store(Request $r){$x=Ride::create($this->data($r));return redirect()->route('rides.index')->with('success','Ride added.');}
 public function update(Request $r,Ride $ride){$ride->update($this->data($r,$ride));return redirect()->route('rides.index')->with('success','Ride updated. New redemptions use the new rate.');}
 public function destroy(Ride $ride){$ride->update(['active'=>false]);$ride->delete();return back()->with('success','Ride removed. Its readers cannot approve new plays.');}
}
