<?php
namespace App\Http\Controllers;
use App\Models\{Package,Sale,Setting};
use App\Services\{SaleService,Qr};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class SaleController {
 public function create(){return view('sales.create',['packages'=>Package::where('active',true)->get(),'requestId'=>(string)Str::uuid()]);}
 public function store(Request $r,SaleService $service){
  $d=$r->validate(['request_id'=>'required|uuid','customer_name'=>'nullable|string|max:100','phone'=>'nullable|string|max:24','existing_credential'=>['nullable','regex:/^QR-[A-F0-9]{32}$/'],'items'=>'required|array|min:1|max:30','items.*.package_id'=>'required|integer|distinct','items.*.quantity'=>'required|integer|min:1|max:99','discount'=>['nullable','regex:/^(0|[1-9]\d{0,7})(\.\d{1,2})?$/'],'received'=>['nullable','regex:/^(0|[1-9]\d{0,7})(\.\d{1,2})?$/'],'payment_method'=>'required|in:cash,upi,card','payment_reference'=>'nullable|string|max:80']);
  $sale=$service->create($d,$r->user());return redirect()->route('sales.show',$sale)->with('success','Bill saved. QR pass is ready to print.');
 }
 public function index(Request $r){
  $d=$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','q'=>'nullable|string|max:100','counter'=>'nullable|integer','export'=>'nullable|in:csv']);
  if(!empty($d['from'])&&!empty($d['to'])&&$d['from']>$d['to'])throw \Illuminate\Validation\ValidationException::withMessages(['to'=>'End date must be on or after the start date.']);
  $query=Sale::with('pass','user')->when($d['from']??null,fn($q,$v)=>$q->where('created_at','>=',$v.' 00:00:00'))->when($d['to']??null,fn($q,$v)=>$q->where('created_at','<=',$v.' 23:59:59'))->when($d['counter']??null,fn($q,$v)=>$q->where('user_id',$v))->when($d['q']??null,fn($q,$v)=>$q->where(function($q)use($v){$q->where('number','like',"%$v%")->orWhereHas('pass',fn($p)=>$p->where('customer_name','like',"%$v%")->orWhere('phone','like',"%$v%"));}));
  if($r->input('export')==='csv'){
   return response()->streamDownload(function()use($query){$f=fopen('php://output','w');fputcsv($f,['Bill','Date','Customer','Payment','Subtotal INR','Discount INR','Tax INR','Total INR','Points','Status']);foreach($query->orderBy('id')->cursor() as $s){$name=$s->receipt_settings['customer_name']??'';if(preg_match('/^[=+@\-\t\r]/',$name))$name="'".$name;fputcsv($f,[$s->number,$s->created_at->format('Y-m-d H:i:s'),$name,$s->payment_method,number_format($s->subtotal_paise/100,2,'.',''),number_format($s->discount_paise/100,2,'.',''),number_format($s->tax_paise/100,2,'.',''),number_format($s->total_paise/100,2,'.',''),$s->points_added,$s->status]);}fclose($f);},'park-sales-'.now()->format('Y-m-d').'.csv',['Content-Type'=>'text/csv']);
  }
  return view('sales.index',['sales'=>$query->latest()->paginate(20)->withQueryString(),'total'=>(clone $query)->where('status','paid')->sum('total_paise')]);
 }
 public function show(Sale $sale){$sale->load('items','pass','user');return view('sales.show',['sale'=>$sale,'qr'=>Qr::svg($sale->pass->credential)]);}
 public function void(Request $r,Sale $sale,SaleService $service){$d=$r->validate(['reason'=>'required|string|min:5|max:255']);$service->void($sale,$d['reason'],$r->user());return back()->with('success','Bill voided and pass blocked. Any money refund must be handled and reconciled separately.');}
}
