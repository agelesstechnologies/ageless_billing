@php($mobileApp = str_contains(request()->userAgent() ?? '', 'AgelessStaffAndroid/'))
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="ageless-user-role" content="{{ auth()->user()->role }}"><meta name="theme-color" content="{{ $park['secondary'] }}"><title>@yield('title','Dashboard') · {{ $park['park_name'] }}</title><link rel="stylesheet" href="{{ asset('assets/app.css') }}"><link rel="stylesheet" href="{{ asset('assets/premium.css') }}"><style>:root{--accent:{{ $park['accent'] }};--secondary:{{ $park['secondary'] }};--yellow:#fed939;--yellow-ink:#180444;}</style>@stack('head')<link rel="stylesheet" href="{{ asset('assets/responsive.css') }}"></head>
<body class="premium @yield('body-class')">
<aside class="sidebar" id="sidebar"><a href="{{ route($mobileApp ? 'mobile.home' : 'dashboard') }}" class="brand"><span class="brand-mark">@include('icon',['name'=>'wheel'])</span><span style="min-width:0;white-space:nowrap;"><strong style="white-space:nowrap;font-size:18px;letter-spacing:-0.4px;">Grand Lemurian<span class="brand-dot">.</span></strong><small style="white-space:nowrap;">BY AGELESS</small></span></a><nav>
<div class="nav-group">YOUR WORKSPACE</div>
<a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route($mobileApp ? 'mobile.home' : 'dashboard') }}">@include('icon',['name'=>'dashboard']) {{ $mobileApp ? 'Team home' : 'Park overview' }}</a>
@if(auth()->user()->role==='admin')
<a class="nav-link {{ request()->routeIs('crm.attendance','crm.detail','attendance.*')?'active':'' }}" href="{{ route('attendance.index') }}">@include('icon',['name'=>'users']) Attendance</a>
<a class="nav-link {{ request()->routeIs('crm.requests','crm.review')?'active':'' }}" href="{{ route('crm.requests') }}">@include('icon',['name'=>'calendar']) Punch requests</a>
@endif
@if(!$mobileApp)
<div class="nav-group">TICKETS & EXPERIENCES</div>
<a class="nav-link {{ request()->routeIs('pos')?'active':'' }}" href="{{ route('pos') }}">@include('icon',['name'=>'pos']) Billing</a>
<a class="nav-link {{ request()->routeIs('sales.*')?'active':'' }}" href="{{ route('sales.index') }}">@include('icon',['name'=>'receipt']) Bills & sales</a>
<a class="nav-link {{ request()->routeIs('passes.*')?'active':'' }}" href="{{ route('passes.index') }}">@include('icon',['name'=>'qr']) QR passes</a>
@endif
@if(auth()->user()->role === 'admin')
@if(!$mobileApp)
<a class="nav-link {{ request()->routeIs('counters.*')?'active':'' }}" href="{{ route('counters.create') }}">@include('icon',['name'=>'plus']) Add ticket counter</a>
<a class="nav-link {{ request()->routeIs('packages.*')?'active':'' }}" href="{{ route('packages.index') }}">@include('icon',['name'=>'ticket']) Packages</a>
<a class="nav-link {{ request()->routeIs('rides.*')?'active':'' }}" href="{{ route('rides.index') }}">@include('icon',['name'=>'wheel']) Rides</a>
<a class="nav-link {{ request()->routeIs('machines.*')?'active':'' }}" href="{{ route('machines.index') }}">@include('icon',['name'=>'machine']) ESP readers</a>
<a class="nav-link {{ request()->routeIs('transactions.*')?'active':'' }}" href="{{ route('transactions.index') }}">@include('icon',['name'=>'activity']) Ride transactions</a>
@endif
<div class="nav-group">TEAM & SETTINGS</div>
<a class="nav-link {{ request()->routeIs('users.*','staff.management')?'active':'' }}" href="{{ route('users.index') }}">@include('icon',['name'=>'users']) Staff Management</a>
<a class="nav-link {{ request()->routeIs('sites.*')?'active':'' }}" href="{{ route('sites.index') }}">@include('icon',['name'=>'pin']) Work site settings</a>
@if(!$mobileApp)
<a class="nav-link {{ request()->routeIs('settings.*')?'active':'' }}" href="{{ route('settings.edit') }}">@include('icon',['name'=>'settings']) Park settings</a>
@endif
@endif
<div class="nav-group">ACCOUNT</div>
<form method="POST" action="{{ route('logout') }}">
@csrf
<button type="submit" class="nav-link sidebar-logout">@include('icon',['name'=>'logout']) Logout</button>
</form>
</nav></aside>
<main class="app-main"><header class="topbar"><div class="breadcrumb"><button type="button" class="icon-btn mobile-toggle" aria-label="Open menu" id="mobile-toggle">@include('icon',['name'=>'menu'])</button><span style="white-space:nowrap;">{{ $park['park_name'] }}</span><span>/</span><strong>@yield('title','Overview')</strong></div><div class="topbar-right"><span class="date">{{ now()->format('D, d M Y') }}</span><span class="badge gray">{{ ucfirst(auth()->user()->role) }} workspace</span></div></header><div class="content">
@if(session('success'))<div class="alert" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@yield('content')</div></main>
<script src="{{ asset('assets/app.js') }}" defer></script>@stack('scripts')</body></html>
