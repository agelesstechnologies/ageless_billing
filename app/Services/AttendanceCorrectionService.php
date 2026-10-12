<?php
namespace App\Services;
use App\Models\{AttendanceCorrection,AttendanceSession,User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class AttendanceCorrectionService {
 public function review(AttendanceCorrection $record,User $admin,array $data):void {
  DB::transaction(function()use($record,$admin,$data){
   // Consistent lock order with punching: employee first, then session/request.
   $user=User::whereKey($record->user_id)->lockForUpdate()->firstOrFail();
   $correction=AttendanceCorrection::whereKey($record->id)->lockForUpdate()->firstOrFail();
   if($correction->user_id===$admin->id) $this->fail('You cannot review your own attendance request. Ask another administrator to review it.');
   if($correction->status!=='pending') $this->fail('This request has already been reviewed.');
   $update=['status'=>$data['decision'],'reviewed_by'=>$admin->id,'review_note'=>$data['review_note'],'reviewed_at'=>CarbonImmutable::now('UTC')];
   if($data['decision']==='approved'){
    $start=$this->parse($data['started_at']??'', $correction->timezone);
    $end=$this->parse($data['ended_at']??'', $correction->timezone);
    if($start->toDateString()!==$correction->work_date) $this->fail('Punch-in must be on the requested work date in the displayed site timezone.');
    if($end->lessThanOrEqualTo($start) || $end->getTimestamp()-$start->getTimestamp()>36*3600) $this->fail('Punch-out must be after punch-in, within 36 hours.');
    if($end->greaterThan(CarbonImmutable::now('UTC'))) $this->fail('Future attendance cannot be approved.');
    $start=$start->utc();$end=$end->utc();
    $session=$correction->attendance_session_id?AttendanceSession::whereKey($correction->attendance_session_id)->where('user_id',$user->id)->lockForUpdate()->firstOrFail():null;
    $overlap=AttendanceSession::where('user_id',$user->id)->when($session,fn($q)=>$q->where('id','!=',$session->id))->where('started_at','<',$end->format('Y-m-d H:i:s'))->where(fn($q)=>$q->whereNull('ended_at')->orWhere('ended_at','>',$start->format('Y-m-d H:i:s')))->exists();
    if($overlap) $this->fail('The proposed times overlap another attendance session. Resolve that session first.');
    $update['before_values']=$session?$this->snapshot($session):null;
    if(!$session){
     $site=$correction->workSite;
     $session=AttendanceSession::create(['user_id'=>$user->id,'work_site_id'=>$site->id,'site_name'=>$site->name,'timezone'=>$correction->timezone,'site_snapshot'=>$site->snapshot(),'work_date'=>$correction->work_date,'started_at'=>$start,'ended_at'=>$end,'source'=>'manual_admin','needs_review'=>false]);
     $update['attendance_session_id']=$session->id;
    }else{
     $session->update(['started_at'=>$start,'ended_at'=>$end,'open_user_id'=>null,'needs_review'=>false,'review_note'=>'Times corrected by administrator. See correction history.']);
    }
    $update['after_values']=$this->snapshot($session);
    // Original punch records and their reported locations are NEVER overwritten.
   }
   $correction->update($update);
   Audit::record('attendance.correction_'.$data['decision'],'attendance_correction',$correction->id,['reason'=>$data['review_note'],'attendance_session_id'=>$update['attendance_session_id']??$correction->attendance_session_id]);
  },3);
 }
 private function parse(string $value,string $tz):CarbonImmutable {
  try{$date=CarbonImmutable::createFromFormat('!Y-m-d\TH:i',$value,$tz);}catch(\Throwable){$this->fail('Enter valid local punch-in and punch-out dates and times.');}
  if(!$date||$date->format('Y-m-d\TH:i')!==$value)$this->fail('Enter valid local punch-in and punch-out dates and times.');
  return $date;
 }
 private function snapshot(AttendanceSession $s):array{return ['started_at'=>$s->started_at->toIso8601String(),'ended_at'=>$s->ended_at?->toIso8601String(),'needs_review'=>$s->needs_review,'source'=>$s->source];}
 private function fail(string $m):never{throw ValidationException::withMessages(['correction'=>$m]);}
}
