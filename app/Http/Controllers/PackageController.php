<?php
namespace App\Http\Controllers;
use App\Domain\Money;
use App\Models\Package;
use App\Services\Audit;
use Illuminate\Http\Request;
class PackageController {
 public function index(){return view('packages.index',['packages'=>Package::orderBy('id')->get()]);}
 public function create(){return view('packages.form',['package'=>new Package(['active'=>true,'points'=>100,'validity_hours'=>24,'kind'=>'package','color'=>'#ff762e','icon'=>'ticket'])]);}
 public function edit(Package $package){return view('packages.form',compact('package'));}
 private function data(Request $r):array{
  $d=$r->validate(['name'=>'required|string|max:100','kind'=>'required|in:ticket,package','description'=>'nullable|string|max:1000','price'=>['required','regex:/^(0|[1-9]\d{0,6})(\.\d{1,2})?$/'],'points'=>'required|integer|min:0|max:1000000','validity_hours'=>'required|integer|min:1|max:8760','color'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],'icon'=>'required|in:ticket,star,rocket,family,waves,planet','active'=>'nullable|boolean']);
  $d['price_paise']=Money::paise($d['price']);unset($d['price']);$d['active']=$r->boolean('active');return $d;
 }
 public function store(Request $r){$p=Package::create($this->data($r));Audit::record('package.created','package',$p->id);return redirect()->route('packages.index')->with('success','Package added.');}
 public function update(Request $r,Package $package){$package->update($this->data($r));Audit::record('package.updated','package',$package->id);return redirect()->route('packages.index')->with('success','Package updated. Existing bills are unchanged.');}
 public function destroy(Package $package){$package->delete();Audit::record('package.deleted','package',$package->id);return back()->with('success','Removed from sale. Historical bills are preserved.');}
}
