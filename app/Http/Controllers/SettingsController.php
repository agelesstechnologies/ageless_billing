<?php
namespace App\Http\Controllers;
use App\Models\Setting;
use App\Services\Audit;
use Illuminate\Http\Request;
class SettingsController {
 public function edit(){return view('settings.edit');}
 public function update(Request $r){
  $d=$r->validate(['park_name'=>'required|string|max:100','tagline'=>'nullable|string|max:160','address'=>'nullable|string|max:500','phone'=>'nullable|string|max:40','tax_id'=>'nullable|string|max:40','accent'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],'secondary'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],'invoice_prefix'=>['required','regex:/^[A-Za-z0-9-]{1,10}$/'],'tax_label'=>'required|string|max:30','tax_rate'=>['required','numeric','min:0','max:100','regex:/^(0|[1-9]\d{0,2})(\.\d{1,2})?$/'],'tax_mode'=>'required|in:inclusive,exclusive','receipt_width'=>'required|in:58,80,82,A4','receipt_footer'=>'nullable|string|max:500','welcome_message'=>'required|string|max:160','logo'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:1024','remove_logo'=>'nullable|boolean']);
  unset($d['logo'],$d['remove_logo']);$current=Setting::current();
  if($r->boolean('remove_logo'))$d['logo']=null;
  if($r->hasFile('logo'))$d['logo']=$r->file('logo')->store('logos','public');
  // Retain old logo files because earlier receipt snapshots still reference them.
  Setting::updateOrCreate(['id'=>1],['data'=>array_replace($current,$d)]);Audit::record('settings.updated','setting',1);
  return back()->with('success','Park settings saved. Existing bill snapshots are unchanged.');
 }
}
