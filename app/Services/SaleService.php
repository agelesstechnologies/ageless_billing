<?php
namespace App\Services;
use App\Domain\Money;
use App\Models\{Package,ParkPass,Sale,PointEntry,Setting,User};
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SaleService {
 public function create(array $data,User $user):Sale {
  $fingerprint=hash('sha256',json_encode([$user->id,$data],JSON_THROW_ON_ERROR));
  $replay=function(Sale $sale)use($fingerprint):Sale{
   if(!hash_equals($sale->fingerprint,$fingerprint)) throw ValidationException::withMessages(['sale'=>'This checkout ID was already used for different details. Start a new bill.']);
   return $sale;
  };
  try {
   return DB::transaction(function()use($data,$user,$fingerprint,$replay){
    if($old=Sale::where('request_id',$data['request_id'])->first()) return $replay($old);
    $settings=Setting::current();$subtotal=0;$points=0;$hours=87600;$lines=[];
    foreach($data['items'] as $item){
     $package=Package::whereKey($item['package_id'])->where('active',true)->first();
     if(!$package) throw ValidationException::withMessages(['items'=>'A selected ticket/package is no longer available.']);
     $q=(int)$item['quantity'];$subtotal+=$package->price_paise*$q;$points+=$package->points*$q;$hours=min($hours,$package->validity_hours);
     $lines[]=['package_id'=>$package->id,'name'=>$package->name,'quantity'=>$q,'unit_price_paise'=>$package->price_paise,'unit_points'=>$package->points,'validity_hours'=>$package->validity_hours];
    }
    if($subtotal>9999999900) throw ValidationException::withMessages(['items'=>'This bill exceeds the supported amount limit. Split it into separate bills.']);
    $discount=Money::paise((string)($data['discount']??'0'));
    if($discount>0 && $user->role!=='admin') throw ValidationException::withMessages(['discount'=>'Only administrators can apply discounts.']);
    if($discount>$subtotal) throw ValidationException::withMessages(['discount'=>'Discount cannot exceed the bill subtotal.']);
    $totals=Money::total($subtotal,$discount,Money::paise($settings['tax_rate']),$settings['tax_mode']);
    if($totals['total_paise']>9999999900) throw ValidationException::withMessages(['items'=>'This bill exceeds the supported amount limit. Split it into separate bills.']);
    $received=$data['payment_method']==='cash' && isset($data['received']) && $data['received']!==''?Money::paise((string)$data['received']):$totals['total_paise'];
    if($received<$totals['total_paise']) throw ValidationException::withMessages(['received'=>'Cash received is less than the bill total.']);
    $expiry=now()->addHours($hours);
    if(!empty($data['existing_credential'])){
     $pass=ParkPass::where('credential',trim($data['existing_credential']))->lockForUpdate()->first();
     if(!$pass||$pass->status!=='active') throw ValidationException::withMessages(['existing_credential'=>'Pass not found or blocked.']);
     if($pass->expires_at->gt($expiry)) $expiry=$pass->expires_at;
     $kind='topup';
    }else{
     $pass=ParkPass::create(['credential'=>'QR-'.strtoupper(bin2hex(random_bytes(16))),'customer_name'=>$data['customer_name']??null,'phone'=>$data['phone']??null,'balance'=>0,'expires_at'=>$expiry,'status'=>'active']);
     $kind='issue';
    }
    if($pass->balance+$points>1000000000) throw ValidationException::withMessages(['items'=>'Pass balance limit exceeded.']);
    $snapshot=$settings;$snapshot['cashier_name']=$user->name;$snapshot['customer_name']=$pass->customer_name;$snapshot['customer_phone']=$pass->phone;$snapshot['pass_expiry']=$expiry->format('d M Y, h:i A');
    $sale=Sale::create($totals+['request_id'=>$data['request_id'],'fingerprint'=>$fingerprint,'park_pass_id'=>$pass->id,'user_id'=>$user->id,'kind'=>$kind,'received_paise'=>$received,'points_added'=>$points,'payment_method'=>$data['payment_method'],'payment_reference'=>$data['payment_reference']??null,'receipt_settings'=>$snapshot]);
    $sale->update(['number'=>$settings['invoice_prefix'].'-'.now()->format('ymd').'-'.str_pad((string)$sale->id,6,'0',STR_PAD_LEFT)]);
    $sale->items()->createMany($lines);
    $pass->update(['balance'=>$pass->balance+$points,'expires_at'=>$expiry]);
    PointEntry::create(['park_pass_id'=>$pass->id,'delta'=>$points,'balance_after'=>$pass->balance,'kind'=>$kind,'sale_id'=>$sale->id,'user_id'=>$user->id,'note'=>$sale->number]);
    Audit::record('sale.created','sale',$sale->id,['number'=>$sale->number,'total_paise'=>$sale->total_paise]);
    return $sale;
   },3);
  }catch(QueryException $e){
   if($old=Sale::where('request_id',$data['request_id'])->first()) return $replay($old);
   throw $e;
  }
 }
 public function void(Sale $sale,string $reason,User $user):void {
  DB::transaction(function()use($sale,$reason,$user){
   // Pass first: the same order used by top-ups and point adjustments.
   $pass=ParkPass::whereKey($sale->park_pass_id)->lockForUpdate()->firstOrFail();
   $bill=Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
   if($bill->status==='voided') return;
   if($bill->kind!=='issue'||$pass->sales()->count()!==1||\App\Models\Redemption::where('park_pass_id',$pass->id)->where('points','>',0)->exists()||$pass->balance!==(int)$bill->points_added||$pass->entries()->count()!==1)
    throw ValidationException::withMessages(['void'=>'Only an untouched first-sale pass can be voided. Used, adjusted or topped-up passes require separate reconciliation.']);
   $pass->update(['balance'=>0,'status'=>'blocked']);
   $bill->update(['status'=>'voided','void_reason'=>$reason,'voided_by'=>$user->id,'voided_at'=>now()]);
   PointEntry::create(['park_pass_id'=>$pass->id,'delta'=>-$bill->points_added,'balance_after'=>0,'kind'=>'void','sale_id'=>$bill->id,'user_id'=>$user->id,'note'=>$reason]);
   Audit::record('sale.voided','sale',$bill->id,['reason'=>$reason]);
  },3);
 }
}
