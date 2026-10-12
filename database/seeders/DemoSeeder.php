<?php
namespace Database\Seeders;
use App\Models\{Package,Ride,Setting};
use Illuminate\Database\Seeder;
class DemoSeeder extends Seeder {
 public function run():void{
  Setting::firstOrCreate(['id'=>1],['data'=>Setting::defaults()]);
  $packages=[
   ['Explorer Entry','ticket','A little ticket to a day of discovery.',25000,100,24,'#cb813b','ticket'],
   ['Adventure Pass','package','More points. More ways to play.',50000,600,24,'#137c77','rocket'],
   ['Family Fun','package','One shared wallet for your whole crew.',150000,1800,24,'#ad74a1','family'],
   ['Splash & Play','package','Make a splash, then keep the fun going.',75000,900,24,'#4b92bc','waves'],
   ['All-Day Thrills','package','A bigger bundle for bigger adventures.',120000,1500,24,'#e88645','star'],
   ['Dome & Discovery','package','Big-screen wonder meets park-day fun.',35000,350,24,'#7777b5','planet'],
  ];
  foreach($packages as [$name,$kind,$description,$price,$points,$hours,$color,$icon])Package::firstOrCreate(['name'=>$name],['kind'=>$kind,'description'=>$description,'price_paise'=>$price,'points'=>$points,'validity_hours'=>$hours,'color'=>$color,'icon'=>$icon,'active'=>true]);
  foreach([['Dome Cinema','DOME',100],['Net Park','NET',80],['Rope Rider','ROPE',150],['VR Adventure','VR',100],['Bumper Cars','BUMPER',80],['Kids Zone','KIDS',50]] as [$name,$code,$points])Ride::firstOrCreate(['code'=>$code],['name'=>$name,'points_per_play'=>$points,'pulse_ms'=>250,'cooldown_seconds'=>5,'active'=>true]);
 }
}
