<?php
namespace App\Services;
use App\Domain\AttendancePolicy;
use App\Models\{AttendanceSession,AttendancePunch,User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class AttendanceService {
 /** Caller validates input. Identity, site and event time ALWAYS come from the server. */
 public function punch(User $actor,array $data,string $ip,string $agent):array {
  $fingerprint=hash('sha256',json_encode([$data['action'],(int)($data['session_id']??0),(float)$data['latitude'],(float)$data['longitude'],(float)$data['accuracy_m'],(string)($data['note']??'')],JSON_THROW_ON_ERROR));
  return DB::transaction(function()use($actor,$data,$ip,$agent,$fingerprint){
   $user=User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
   abort_unless($user->active,403);
   $prior=AttendancePunch::where('request_id',$data['request_id'])->first();
   if($prior){
    abort_unless($prior->user_id===$user->id && hash_equals($prior->fingerprint,$fingerprint),409,'Request identifier was already used for another punch.');
    return $this->response($prior,true);
   }
   $now=CarbonImmutable::now('UTC');
   if($data['action']==='in'){
    if(!$user->attendance_enabled) $this->fail('Attendance has not been enabled for your account. Contact your administrator.');
    $site=$user->workSite;
    if(!$site || !$site->active) $this->fail('No active work site is assigned to your account.');
    if(AttendanceSession::where('open_user_id',$user->id)->exists()) $this->fail('You already have an open shift. Refresh this page; punch out before starting another shift.');
    $snapshot=$site->snapshot();
   }else{
    // A target session ID prevents a delayed retry from closing a later/new shift.
    $session=AttendanceSession::whereKey($data['session_id'])->where('user_id',$user->id)->lockForUpdate()->firstOrFail();
    if($session->ended_at) $this->fail('This shift is already closed. Refresh this page to see its latest status.');
    $snapshot=$session->site_snapshot;
   }
   $geo=AttendancePolicy::location($snapshot,(float)$data['latitude'],(float)$data['longitude'],(float)$data['accuracy_m'],$data['action']);
   if(!$geo['allowed']) throw ValidationException::withMessages(['location'=>$geo['message']]);
   if($data['action']==='in'){
    $session=AttendanceSession::create(['user_id'=>$user->id,'open_user_id'=>$user->id,'work_site_id'=>$site->id,'site_name'=>$site->name,'timezone'=>$site->timezone,'site_snapshot'=>$snapshot,'work_date'=>$now->setTimezone($site->timezone)->toDateString(),'started_at'=>$now,'source'=>'browser']);
   }else{
    $tooLong=$now->getTimestamp()-$session->started_at->getTimestamp()>86400;
    $review=$geo['needs_review']||$tooLong;
    $note=implode(' ',array_filter([$geo['needs_review']?$geo['message']:null,$tooLong?'Shift exceeded 24 hours; verify the effective times.':null]));
    $session->update(['ended_at'=>$now,'open_user_id'=>null,'needs_review'=>$review,'review_note'=>$note?:null]);
   }
   $punch=AttendancePunch::create(['attendance_session_id'=>$session->id,'user_id'=>$user->id,'request_id'=>$data['request_id'],'fingerprint'=>$fingerprint,'type'=>$data['action'],'punched_at'=>$now,'latitude'=>$data['latitude'],'longitude'=>$data['longitude'],'accuracy_m'=>$data['accuracy_m'],'distance_m'=>$geo['distance_m'],'location_status'=>$geo['status'],'ip_address'=>$ip,'user_agent'=>substr($agent,0,255),'note'=>$data['note']??null]);

   return $this->response($punch,false);
  },3);
 }
 private function fail(string $message):never{throw ValidationException::withMessages(['attendance'=>$message]);}
 private function response(AttendancePunch $punch,bool $replay):array{
  $session=$punch->session;
  return ['ok'=>true,'replay'=>$replay,'punch_id'=>$punch->id,'session_id'=>$session->id,'action'=>$punch->type,'recorded_at'=>$punch->punched_at->toIso8601String(),'needs_review'=>$session->needs_review,'message'=>$punch->type==='in'?'Punch-in recorded. Have a great day!':($session->needs_review?'Punch-out recorded and flagged for administrator review.':'Punch-out recorded. Thank you for today!'),'redirect'=>route('staff.home')];
 }
}
