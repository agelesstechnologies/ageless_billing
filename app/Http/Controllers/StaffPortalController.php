<?php
namespace App\Http\Controllers;
use App\Domain\AttendancePolicy;
use App\Models\{AttendanceSession,AttendanceCorrection,User};
use App\Services\{AttendanceService};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use App\Rules\StrongPassword;
use Illuminate\Validation\ValidationException;
class StaffPortalController {
 public function home(Request $r){
  $user=$r->user()->load('workSite');
  $tz=$user->workSite?->timezone??config('app.timezone');$now=CarbonImmutable::now('UTC');$today=$now->setTimezone($tz)->toDateString();
  $active=AttendanceSession::where('open_user_id',$user->id)->with('punches')->first();
  $tz=$active?->timezone??$tz;$today=$now->setTimezone($tz)->toDateString();
  $sessions=AttendanceSession::where('user_id',$user->id)->where('work_date',$today)->with('punches')->orderBy('started_at')->get();
  $closedSeconds=$sessions->sum(fn($s)=>$s->closedSeconds());
  $todayIn=$sessions->first()?->localTime('started_at')??$active?->localTime('started_at');
  $todayOut=$sessions->whereNotNull('ended_at')->last()?->localTime('ended_at');
  return view('staff.home',compact('user','tz','now','today','active','sessions','closedSeconds','todayIn','todayOut'));
 }
 public function punch(Request $r,AttendanceService $service){
  $data=$r->validate(['action'=>'required|in:in,out','request_id'=>'required|uuid','session_id'=>'nullable|required_if:action,out|integer|min:1','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','accuracy_m'=>'required|numeric|between:0,50000','note'=>'nullable|string|max:500']);
  return response()->json($service->punch($r->user(),$data,(string)$r->ip(),(string)$r->userAgent()));
 }
 public function history(Request $r){
  $filters=$r->validate(['month'=>'nullable|date_format:Y-m']);$tz=$r->user()->workSite?->timezone??config('app.timezone');$month=$filters['month']??now($tz)->format('Y-m');
  $start=CarbonImmutable::createFromFormat('!Y-m',$month,$tz);$sessions=AttendanceSession::where('user_id',$r->user()->id)->whereBetween('work_date',[$start->toDateString(),$start->endOfMonth()->toDateString()])->with('punches')->orderByDesc('started_at')->paginate(31)->withQueryString();
  return view('staff.history',compact('sessions','month','tz'));
 }
 public function show(Request $r,AttendanceSession $attendance){
  abort_unless($attendance->user_id===$r->user()->id,404);$attendance->load('punches','corrections.reviewer');return view('staff.detail',compact('attendance'));
 }
 public function requests(Request $r){
  $requests=AttendanceCorrection::where('user_id',$r->user()->id)->with('reviewer','workSite')->latest()->paginate(12);
  $sessions=AttendanceSession::where('user_id',$r->user()->id)->latest('started_at')->limit(60)->get();
  if($r->filled('session') && ctype_digit((string)$r->input('session'))){$selected=AttendanceSession::where('user_id',$r->user()->id)->find($r->input('session'));if($selected && !$sessions->contains('id',$selected->id))$sessions->prepend($selected);}
  return view('staff.requests',compact('requests','sessions'));
 }
 public function requestCorrection(Request $r){
  $data=$r->validate(['work_date'=>'required|date_format:Y-m-d','attendance_session_id'=>'nullable|integer|min:1','reason'=>'required|string|min:10|max:1500']);
  DB::transaction(function()use($r,$data){
   $user=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   $session=empty($data['attendance_session_id'])?null:AttendanceSession::whereKey($data['attendance_session_id'])->where('user_id',$user->id)->firstOrFail();
   $site=$session?->workSite??$user->workSite;$tz=$session?->timezone??$site?->timezone;
   if(!$site) throw ValidationException::withMessages(['work_date'=>'Ask your administrator to assign a work site first.']);
   $day=$session?->work_date??$data['work_date'];
   if($day>CarbonImmutable::now($tz)->toDateString())throw ValidationException::withMessages(['work_date'=>'Future attendance cannot be requested.']);
   if($session && $data['work_date']!==$session->work_date)throw ValidationException::withMessages(['work_date'=>'Choose the same date as the selected attendance session.']);
   if(AttendanceCorrection::where('user_id',$user->id)->where('work_date',$day)->where('status','pending')->exists()) throw ValidationException::withMessages(['reason'=>'You already have a pending request for this date.']);
   $record=AttendanceCorrection::create(['user_id'=>$user->id,'attendance_session_id'=>$session?->id,'work_site_id'=>$site->id,'work_date'=>$day,'timezone'=>$tz,'reason'=>$data['reason']]);

  });return back()->with('success','Request sent to your administrator. Your original punches have not changed.');
 }
 public function profile(Request $r){return view('staff.profile',['user'=>$r->user()->load('workSite')]);}
 public function password(Request $r){
  $data=$r->validate(['current_password'=>'required|string','password'=>['required','confirmed',new StrongPassword]]);
  if(!Hash::check($data['current_password'],$r->user()->password))throw ValidationException::withMessages(['current_password'=>'Current password is incorrect.']);
  $r->user()->update(['password'=>$data['password']]);$r->session()->regenerate();$r->session()->put('password_stamp',hash('sha256',$r->user()->password));
  return back()->with('success','Password changed. Other sessions will be signed out on their next request.');
 }
}
