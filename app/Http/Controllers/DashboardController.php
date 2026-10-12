<?php
namespace App\Http\Controllers;
use App\Models\{Sale,ParkPass,Machine,Redemption};
class DashboardController {
 public function __invoke(){
  $today=now()->startOfDay();
  return view('dashboard',[
   'revenue'=>Sale::where('status','paid')->where('created_at','>=',$today)->sum('total_paise'),
   'bills'=>Sale::where('status','paid')->where('created_at','>=',$today)->count(),
   'activePasses'=>ParkPass::where('status','active')->where('expires_at','>',now())->count(),
   'plays'=>Redemption::where('status','completed')->where('created_at','>=',$today)->count(),
   'recent'=>Sale::with('pass','user')->latest()->limit(6)->get(),
   'machines'=>Machine::with('ride')->orderBy('name')->get(),
   'onDuty'=>\App\Models\AttendanceSession::whereNotNull('open_user_id')->count(),
   'attendanceRequests'=>\App\Models\AttendanceCorrection::where('status','pending')->count(),
   'pending'=>Redemption::whereIn('status',['approved','uncertain'])->count(),
  ]);
 }
}
