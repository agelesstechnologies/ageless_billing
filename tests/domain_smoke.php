<?php
// Framework-independent checks. Run with: php tests/domain_smoke.php
require __DIR__.'/../app/Domain/Money.php';
require __DIR__.'/../app/Domain/RedemptionPolicy.php';
use App\Domain\Money;
use App\Domain\RedemptionPolicy as Policy;
$count=0;
function check(bool $condition,string $label):void{global $count;$count++;if(!$condition){fwrite(STDERR,"FAIL: $label\n");exit(1);}}
foreach(['0'=>0,'0.01'=>1,'1'=>100,'1.5'=>150,'18.00'=>1800,'99999999.99'=>9999999999] as $value=>$expected)check(Money::paise((string)$value)===$expected,"parse $value");
foreach(['','-1','+1','1,000','1e3','1.234','NaN','01','1.','100000000','0.001'] as $value){$threw=false;try{Money::paise($value);}catch(InvalidArgumentException $e){$threw=true;}check($threw,"reject $value");}
check(Money::display(123456)==='1,234.56','format integer paise');
check(Money::total(50000,0,1800,'exclusive')===['subtotal_paise'=>50000,'discount_paise'=>0,'tax_paise'=>9000,'total_paise'=>59000],'exclusive tax');
check(Money::total(59000,0,1800,'inclusive')['tax_paise']===9000,'inclusive tax');
check(Money::total(50000,5000,1800,'exclusive')['total_paise']===53100,'discount before exclusive tax');
check(Money::total(59000,59000,1800,'inclusive')['total_paise']===0,'fully discounted bill');
check(Money::total(1,0,5000,'exclusive')['tax_paise']===1,'half-up rounding');
foreach([[100,101,0,'exclusive'],[-1,0,0,'exclusive'],[100,-1,0,'exclusive'],[100,0,10001,'exclusive'],[100,0,0,'bad']] as $args){$threw=false;try{Money::total(...$args);}catch(InvalidArgumentException $e){$threw=true;}check($threw,'reject invalid accounting parameters');}
$base=['enabled'=>true,'rideActive'=>true,'exists'=>true,'status'=>'active','expiry'=>2000,'now'=>1000,'balance'=>100,'cost'=>100,'pending'=>false,'cooldown'=>false];
$deny=fn(array $overrides=[])=>Policy::deny(...array_replace($base,$overrides));
check($deny()===null,'exact balance allowed');
foreach([
 [['enabled'=>false],'MACHINE_DISABLED'],[['rideActive'=>false],'RIDE_DISABLED'],[['exists'=>false],'INVALID_QR'],[['status'=>'blocked'],'PASS_BLOCKED'],[['expiry'=>1000],'PASS_EXPIRED'],[['expiry'=>999],'PASS_EXPIRED'],[['balance'=>99],'INSUFFICIENT_POINTS'],[['pending'=>true],'MACHINE_PENDING'],[['cooldown'=>true],'COOLDOWN'],
] as [$override,$expected])check($deny($override)===$expected,$expected);
check(Policy::mayPulse('approved',1000,1000),'approval start');check(Policy::mayPulse('approved',1000,1029),'approval inside window');check(!Policy::mayPulse('approved',1000,1030),'approval expired');check(!Policy::mayPulse('completed',1000,1001),'completed never re-pulses');check(!Policy::mayPulse('uncertain',1000,1001),'uncertain never pulses');check(!Policy::mayPulse('reversed',1000,1001),'reversed never pulses');check(!Policy::mayPulse('approved',1000,999),'future timestamp denied');
mt_srand(20261006);
for($i=0;$i<1000;$i++){
 $subtotal=mt_rand(0,10000000);$discount=mt_rand(0,$subtotal);$rate=mt_rand(0,10000);$net=$subtotal-$discount;
 foreach(['inclusive','exclusive'] as $mode){$result=Money::total($subtotal,$discount,$rate,$mode);$den=$mode==='inclusive'?10000+$rate:10000;$tax=(int)floor($net*$rate/$den+0.5);check($result['tax_paise']===$tax,"random tax case $i/$mode");check($result['total_paise']===($mode==='inclusive'?$net:$net+$tax),"random total case $i/$mode");check($result['total_paise']>=0,"nonnegative bill $i/$mode");}
}
echo "PASS: $count domain assertions (money parsing, integer tax, discounts, denial policies, approval time window).\n";
echo "These checks do not execute Laravel, SQL transactions, QR generation or ESP hardware.\n";
