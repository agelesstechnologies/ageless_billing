<?php
namespace App\Http\Controllers;

use App\Models\{User,AttendanceSession,AttendanceCorrection};
use Illuminate\Http\Request;

class MobilePortalController {
    public function home(Request $request) {
        // The authenticated user and role are read by Laravel from the same
        // configured project database used for users, billing and attendance.
        if ($request->user()->role !== 'admin') return redirect()->route('staff.home');
        return view('mobile.admin', [
            'user' => $request->user(),
            'employeeCount' => User::where('role','staff')->count(),
            'activeCount' => User::where('active',true)->count(),
            'onDutyCount' => AttendanceSession::whereNotNull('open_user_id')->count(),
            'requestCount' => AttendanceCorrection::where('status','pending')->count(),
        ]);
    }
}
