<?php
namespace App\Http\Controllers;
use App\Models\WorkSite;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class WorkSiteController {
 public function index(){return view('sites.index',['sites'=>WorkSite::withCount('users')->orderBy('name')->get()]);}
 public function create(){return view('sites.form',['site'=>new WorkSite(['timezone'=>'Asia/Kolkata','radius_m'=>200,'max_accuracy_m'=>100,'active'=>true,'enforce_geofence'=>false])]);}
 public function edit(WorkSite $site){return view('sites.form',compact('site'));}
 public function store(Request $r){$site=WorkSite::create($this->data($r));return redirect()->route('sites.index')->with('success','Work site created. Assign team members under Staff access.');}
 public function update(Request $r,WorkSite $site){$site->update($this->data($r));return redirect()->route('sites.index')->with('success','Work site saved. Existing attendance keeps its original site-policy snapshot.');}
 private function data(Request $r):array{
  $d=$r->validate(['name'=>'required|string|max:100','address'=>'nullable|string|max:500','timezone'=>'required|timezone:all','latitude'=>'nullable|required_with:longitude|required_if:enforce_geofence,1|numeric|between:-90,90','longitude'=>'nullable|required_with:latitude|required_if:enforce_geofence,1|numeric|between:-180,180','radius_m'=>'required|integer|between:20,10000','max_accuracy_m'=>'required|integer|between:5,5000','enforce_geofence'=>'required|boolean','active'=>'required|boolean']);
  $d['enforce_geofence']=$r->boolean('enforce_geofence');$d['active']=$r->boolean('active');return $d;
 }
}
