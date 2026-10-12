<?php
namespace Database\Seeders;
use App\Models\{Machine,Package,ParkPass,PointEntry,Redemption,Ride,Sale,User};
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use App\Rules\StrongPassword;
use Illuminate\Support\Str;
class DemoBillingSeeder extends Seeder {
 public function run():void{
  $this->call(DemoSeeder::class);
  if(Sale::exists()){$this->command?->warn('Sales already exist; skipping demo billing data.');return;}
  $admin=User::where('role','admin')->first()??User::create(['name'=>'Main Admin','email'=>'admin@example.com','password'=>StrongPassword::generate(),'role'=>'admin','active'=>true]);
  $cashierPassword=StrongPassword::generate();
  $cashier=User::firstOrCreate(['email'=>'cashier@example.com'],['name'=>'Demo Cashier','password'=>$cashierPassword,'role'=>'cashier','active'=>true]);
  if($cashier->wasRecentlyCreated)$this->command?->info('Demo cashier cashier@example.com password (shown once): '.$cashierPassword);
  $pk=fn(string $n)=>Package::where('name',$n)->value('id');
  $svc=new SaleService;
  // [days ago, user, customer, phone, items[name=>qty], method, ref, existing pass index]
  $bills=[
   [6,$cashier,'Anil Kumar','9847000001',['Explorer Entry'=>2],'cash',null,null],
   [5,$cashier,'Meera Nair','9847000002',['Family Fun'=>1],'upi','UPI4829301',null],
   [4,$admin,'Joseph Mathew','9847000003',['Adventure Pass'=>2,'Splash & Play'=>1],'card','CARD-7731',null],
   [3,$cashier,'Priya Menon','9847000004',['All-Day Thrills'=>1],'cash',null,null],
   [2,$cashier,'Rahul S','9847000005',['Dome & Discovery'=>2],'upi','UPI5520178',null],
   [2,$cashier,null,null,['Explorer Entry'=>4],'cash',null,null],
   [1,$admin,'Fathima Beevi','9847000006',['Family Fun'=>1,'Explorer Entry'=>2],'card','CARD-9012',null],
   [1,$cashier,'Anil Kumar','9847000001',['Adventure Pass'=>1],'upi','UPI6671044',0],
   [0,$cashier,'Sneha Raj','9847000007',['Splash & Play'=>2],'cash',null,null],
   [0,$cashier,'Vishnu P','9847000008',['All-Day Thrills'=>1,'Dome & Discovery'=>1],'upi','UPI7781290',null],
  ];
  $passes=[];$sales=[];
  foreach($bills as [$days,$user,$name,$phone,$items,$method,$ref,$existing]){
   $data=['request_id'=>(string)Str::uuid(),'customer_name'=>$name,'phone'=>$phone,'payment_method'=>$method,'payment_reference'=>$ref,
    'items'=>collect($items)->map(fn($q,$n)=>['package_id'=>$pk($n),'quantity'=>$q])->values()->all()];
   if($method==='cash')$data['received']=(string)(ceil($this->estimate($items)/100)*100);
   if($existing!==null)$data['existing_credential']=$passes[$existing]->credential;
   $sale=$svc->create($data,$user);
   $when=now()->subDays($days)->setTime(rand(10,19),rand(0,59));
   $sale->forceFill(['created_at'=>$when,'updated_at'=>$when])->save();
   PointEntry::where('sale_id',$sale->id)->update(['created_at'=>$when,'updated_at'=>$when]);
   $sale->pass->forceFill(['created_at'=>$when])->save();
   $passes[]=$sale->pass;$sales[]=$sale;
  }
  // Ride usage so balances and history look realistic.
  $rides=Ride::all()->keyBy('code');
  $machines=$rides->map(fn($r)=>Machine::firstOrCreate(['serial'=>'DEMO-'.$r->code],['name'=>$r->name.' Reader','ride_id'=>$r->id,'token_hash'=>hash('sha256','demo-'.$r->code),'enabled'=>true]));
  $uses=[[0,'DOME'],[0,'NET'],[1,'VR'],[1,'ROPE'],[2,'BUMPER'],[3,'DOME'],[4,'KIDS'],[6,'NET'],[6,'VR']];
  foreach($uses as [$i,$code]){
   $pass=$passes[$i]->fresh();$ride=$rides[$code];
   if($pass->balance<$ride->points_per_play)continue;
   $at=$sales[$i]->created_at->copy()->addMinutes(rand(20,120));
   $tx=Redemption::create(['machine_id'=>$machines[$code]->id,'request_id'=>(string)Str::uuid(),'credential_hash'=>hash('sha256',$pass->credential),'park_pass_id'=>$pass->id,'ride_id'=>$ride->id,'ride_name'=>$ride->name,'status'=>'completed','code'=>'COMPLETED','points'=>$ride->points_per_play,'balance_after'=>$pass->balance-$ride->points_per_play,'pulse_ms'=>$ride->pulse_ms,'authorized_at'=>$at,'acknowledged_at'=>$at,'created_at'=>$at,'updated_at'=>$at]);
   $pass->update(['balance'=>$pass->balance-$ride->points_per_play]);
   PointEntry::create(['park_pass_id'=>$pass->id,'delta'=>-$ride->points_per_play,'balance_after'=>$pass->balance,'kind'=>'redeem','redemption_id'=>$tx->id,'note'=>$ride->name,'created_at'=>$at,'updated_at'=>$at]);
  }
  // One voided bill (untouched first sale), as an admin correction.
  $void=$svc->create(['request_id'=>(string)Str::uuid(),'customer_name'=>'Test Entry','phone'=>null,'payment_method'=>'cash','received'=>'250','items'=>[['package_id'=>$pk('Explorer Entry'),'quantity'=>1]]],$cashier);
  $svc->void($void,'Entered by mistake',$admin);
 }
 private function estimate(array $items):int{$t=0;foreach($items as $n=>$q)$t+=Package::where('name',$n)->value('price_paise')*$q;return intdiv($t,100);}
}
