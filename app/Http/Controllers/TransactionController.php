<?php
namespace App\Http\Controllers;
use App\Models\Redemption;
use App\Services\RedemptionService;
use Illuminate\Http\Request;
class TransactionController {
 public function index(Request $r){$d=$r->validate(['status'=>'nullable|in:approved,uncertain,completed,denied,reversed,pending']);$q=Redemption::with('machine','pass');if(($d['status']??'')==='pending')$q->whereIn('status',['approved','uncertain']);elseif(!empty($d['status']))$q->where('status',$d['status']);return view('transactions.index',['transactions'=>$q->latest()->paginate(30)->withQueryString()]);}
 public function resolve(Request $r,Redemption $transaction,RedemptionService $service){$d=$r->validate(['action'=>'required|in:confirm,refund','note'=>'required|string|min:10|max:255','reader_powered_off'=>'accepted']);$service->resolve($transaction,$d['action'],$d['note'],$r->user());return back()->with('success','Transaction resolved. Reboot and re-enable the reader after checking its local pending state.');}
}
