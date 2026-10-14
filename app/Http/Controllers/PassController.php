<?php
namespace App\Http\Controllers;
use App\Models\{ParkPass,PointEntry};
use App\Services\{Qr};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PassController {
 public function index(Request $r){$d=$r->validate(['q'=>'nullable|string|max:100']);$q=ParkPass::query();if($v=$d['q']??null)$q->where(fn($q)=>$q->where('credential',$v)->orWhere('customer_name','like',"%$v%")->orWhere('phone','like',"%$v%"));return view('passes.index',['passes'=>$q->latest()->paginate(20)->withQueryString()]);}
 public function show(ParkPass $pass){return view('passes.show',['pass'=>$pass,'entries'=>$pass->entries()->with('sale','redemption')->latest()->paginate(30),'qr'=>Qr::svg($pass->credential)]);}
 public function update(Request $r,ParkPass $pass){
  $d=$r->validate(['status'=>'required|in:active,blocked','reason'=>'required|string|min:5|max:255']);
  DB::transaction(function()use($d,$pass){$p=ParkPass::whereKey($pass->id)->lockForUpdate()->firstOrFail();$p->update(['status'=>$d['status']]);});
  return back()->with('success','Pass status updated. Previously issued device approvals are not remotely cancelled.');
 }
 public function adjust(Request $r,ParkPass $pass){
  $d=$r->validate(['delta'=>'required|integer|between:-1000000,1000000|not_in:0','reason'=>'required|string|min:5|max:255']);
  DB::transaction(function()use($d,$pass,$r){$p=ParkPass::whereKey($pass->id)->lockForUpdate()->firstOrFail();$new=$p->balance+(int)$d['delta'];if($new<0||$new>1000000000)throw ValidationException::withMessages(['delta'=>'Adjustment would exceed the allowed balance.']);$p->update(['balance'=>$new]);PointEntry::create(['park_pass_id'=>$p->id,'delta'=>$d['delta'],'balance_after'=>$new,'kind'=>'adjustment','user_id'=>$r->user()->id,'note'=>$d['reason']]);});
  return back()->with('success','Point adjustment recorded. No payment was collected.');
 }
}
