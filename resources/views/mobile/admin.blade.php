<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}"><meta name="ageless-user-role" content="{{ $user->role }}">
<meta name="theme-color" content="#180444"><title>Team management · {{ $park['park_name'] }}</title>
<link rel="stylesheet" href="{{ asset('assets/app.css') }}"><link rel="stylesheet" href="{{ asset('assets/premium.css') }}"><link rel="stylesheet" href="{{ asset('assets/staff.css') }}">
<style>
:root{--accent:#7a31b7;--secondary:#180444;--yellow:#fed939;--yellow-ink:#180444}
.admin-welcome{background:#180444;color:#fff;padding:26px 22px;border-radius:22px}.admin-welcome h1{color:#fff;font-size:27px;margin:10px 0;line-height:1.2}.admin-welcome p{color:#dfc7f5;margin:0}.admin-label{font-size:10px;letter-spacing:1.5px;color:#fed939;font-weight:800}.admin-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:18px 0}.admin-stat{background:#fff;border:1px solid #e8ddf8;border-radius:18px;padding:18px}.admin-stat strong{display:block;font-size:28px;color:#180444}.admin-stat span{font-size:12px;color:#6f618f}.admin-actions{display:grid;gap:12px}.admin-action{display:flex;gap:14px;align-items:center;background:#fff;border:1px solid #e8ddf8;border-radius:18px;padding:18px;color:#180444;text-decoration:none}.admin-action .support-icon{flex-shrink:0;background:#efe7fc;color:#7a31b7}.admin-action strong{display:block;font-size:15px}.admin-action small{display:block;color:#6f618f;font-size:12px;margin-top:4px}.admin-action.primary{background:#7a31b7;color:#fff;border-color:#7a31b7}.admin-action.primary small{color:#ead9f8}.admin-action.primary .support-icon{background:#fff;color:#7a31b7}.admin-footer{font-size:12px;color:#6f618f;line-height:1.7;margin:22px 2px}.admin-signout{margin-bottom:24px}
</style></head>
<body class="staff-body"><div class="staff-shell">
<header class="staff-header"><a class="staff-brand" href="{{ route('mobile.home') }}"><span class="brand-mark">@include('icon',['name'=>'users'])</span><span><strong>{{ $park['park_name'] }}</strong><small>TEAM MANAGEMENT</small></span></a><a class="staff-avatar" href="{{ route('staff.profile') }}" aria-label="My profile">{{ strtoupper(substr($user->name,0,1)) }}</a></header>
<main class="staff-main">
<section class="admin-welcome"><span class="admin-label">ADMINISTRATOR</span><h1>Hello, {{ $user->name }}</h1><p>Manage your team and their attendance.</p></section>
<div class="admin-grid">
<div class="admin-stat"><strong>{{ $employeeCount }}</strong><span>Staff accounts</span></div><div class="admin-stat"><strong>{{ $activeCount }}</strong><span>Active accounts</span></div>
<div class="admin-stat"><strong>{{ $onDutyCount }}</strong><span>On duty now</span></div><div class="admin-stat"><strong>{{ $requestCount }}</strong><span>Pending requests</span></div>
</div>
<section class="admin-actions">
<a class="admin-action primary" href="{{ route('users.create') }}"><span class="support-icon">@include('icon',['name'=>'users'])</span><span><strong>Add employee</strong><small>Create their account and login password.</small></span></a>
<a class="admin-action" href="{{ route('users.index') }}"><span class="support-icon">@include('icon',['name'=>'users'])</span><span><strong>Manage employees</strong><small>Update details, passwords and account status.</small></span></a>
<a class="admin-action" href="{{ route('attendance.index') }}"><span class="support-icon">@include('icon',['name'=>'calendar'])</span><span><strong>Attendance register</strong><small>See punch times, GPS location and working hours.</small></span></a>
<a class="admin-action" href="{{ route('crm.requests') }}"><span class="support-icon">@include('icon',['name'=>'receipt'])</span><span><strong>Punch requests</strong><small>Review missed or incorrect punches.</small></span></a>
<a class="admin-action" href="{{ route('sites.index') }}"><span class="support-icon">@include('icon',['name'=>'pin'])</span><span><strong>Work sites</strong><small>Assign sites and set the permitted location radius.</small></span></a>
</section>
<p class="admin-footer">Employee accounts and attendance are shared with your ERP. New staff use the email and password you assign to sign in to Ageless Staff.</p>
<form class="admin-signout" method="POST" action="{{ route('logout') }}">@csrf<button class="btn full">Sign out</button></form>
</main>
<nav class="staff-tabs" aria-label="Admin navigation"><a class="selected" href="{{ route('mobile.home') }}">@include('icon',['name'=>'home'])<span>Home</span></a><a href="{{ route('users.index') }}">@include('icon',['name'=>'users'])<span>Employees</span></a><a href="{{ route('attendance.index') }}">@include('icon',['name'=>'calendar'])<span>Attendance</span></a><a href="{{ route('staff.profile') }}">@include('icon',['name'=>'users'])<span>Profile</span></a></nav>
</div></body></html>
