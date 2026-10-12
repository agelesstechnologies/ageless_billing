<?php
require __DIR__.'/../app/Domain/AttendancePolicy.php';
require __DIR__.'/../app/Support/Csv.php';
use App\Domain\AttendancePolicy as P;
use App\Support\Csv;
$count=0;
function check(bool $condition,string $name):void{global $count;if(!$condition){fwrite(STDERR,"FAIL: $name\n");exit(1);}$count++;}
function rejects(callable $fn,string $name):void{$thrown=false;try{$fn();}catch(InvalidArgumentException){$thrown=true;}check($thrown,$name);}
check(P::distance(0,0,0,0)===0.0,'zero distance');
check(abs(P::distance(0,0,1,0)-111195.080)<0.01,'one degree latitude');
check(abs(P::distance(0,0,0,180)-20015114.442)<0.1,'antipodal distance is finite');
check(P::distance(10,179.999,10,-179.999)<220,'date-line crossing');
rejects(fn()=>P::distance(91,0,0,0),'invalid latitude');rejects(fn()=>P::distance(0,181,0,0),'invalid longitude');rejects(fn()=>P::distance(NAN,0,0,0),'NaN coordinate');
$site=['latitude'=>0,'longitude'=>0,'radius_m'=>200,'max_accuracy_m'=>100,'enforce_geofence'=>true];
$r=P::location($site,0,0,10,'in');check($r['allowed']&&$r['status']==='inside'&&$r['distance_m']===0,'zero coordinate is a configured site');
$r=P::location($site,0.01,0,10,'in');check(!$r['allowed']&&$r['status']==='outside','outside in denied');
$r=P::location($site,0.01,0,10,'out');check($r['allowed']&&$r['needs_review']&&$r['status']==='outside','outside out recorded for review');
$r=P::location($site,0,0,101,'in');check(!$r['allowed']&&$r['status']==='poor_accuracy','poor accuracy in denied');
$r=P::location($site,0,0,100,'in');check($r['allowed'],'accuracy threshold inclusive');
$r=P::location($site,0,0,101,'out');check($r['allowed']&&$r['needs_review'],'poor accuracy out flagged');
$unconfigured=array_replace($site,['latitude'=>null,'longitude'=>null]);
$r=P::location($unconfigured,0,0,10,'in');check(!$r['allowed']&&$r['status']==='not_configured','unconfigured enforced in denied');
$r=P::location(array_replace($unconfigured,['enforce_geofence'=>false]),10,76,10,'in');check($r['allowed']&&$r['distance_m']===null&&$r['status']==='recorded','record only without centre');
$r=P::location(array_replace($site,['enforce_geofence'=>false]),10,76,10,'in');check($r['allowed'],'record only does not enforce radius');
$justOutside=200.1/111195.080;
$r=P::location($site,$justOutside,0,1,'in');check(!$r['allowed'],'compare unrounded radius distance');
rejects(fn()=>P::location($site,0,0,-1,'in'),'negative accuracy');rejects(fn()=>P::location($site,0,0,INF,'in'),'infinite accuracy');rejects(fn()=>P::location($site,0,0,1,'update'),'unknown action');
check(P::seconds(100,3700)===3600,'one hour');check(P::seconds(100,null)===0,'open not counted as completed');check(P::seconds(200,100)===0,'negative duration clamped');
check(P::duration(86399)==='23h 59m','display floors complete minutes');check(P::duration(90061)==='25h 01m','overnight not wrapped to 24h');check(P::duration(0)==='0h 00m','zero duration');
check(P::overlaps(10,20,15,30),'overlap');check(!P::overlaps(10,20,20,30),'adjacent intervals allowed');check(P::overlaps(10,null,20,30),'open interval blocks overlap');check(!P::overlaps(10,20,30,null),'later open shift not overlap');
foreach(['=1+1','+SUM(A1:A2)','@SUM(A1:A2)','-3+1',"\t=1",'  +1'] as $v)check(str_starts_with(Csv::cell($v),"'"),'CSV formula escaped');
foreach(['Arun','12','10.123','TEAM-001','',null] as $v)check(Csv::cell($v)===(string)$v,'CSV ordinary data retained');
mt_srand(4821);
for($i=0;$i<500;$i++){
 $a=mt_rand(-890000,890000)/10000;$b=mt_rand(-1790000,1790000)/10000;$c=mt_rand(-890000,890000)/10000;$d=mt_rand(-1790000,1790000)/10000;
 $dist=P::distance($a,$b,$c,$d);check(is_finite($dist)&&$dist>=0&&$dist<=20015115,'distance physical bounds');check(abs($dist-P::distance($c,$d,$a,$b))<0.0001,'distance symmetric');check(P::distance($a,$b,$a,$b)<0.00001,'identity distance');
}
echo "PASS: $count attendance domain / CSV assertions. No Laravel or database was executed.\n";
