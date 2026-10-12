@extends('layouts.staff')
@section('title','My day')
@section('content')
@php
$siteName = $active?->site_name ?? $user->workSite?->name ?? 'Main park';
// An open shift can always be closed; a new one needs attendance enabled and a site assigned by an administrator.
$canPunch = (bool)$active || ($user->attendance_enabled && $user->work_site_id);
$hour = (int)$now->setTimezone($tz)->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

<section class="staff-welcome">
    <div class="staff-eyebrow">{{ $now->setTimezone($tz)->format('l, d F Y') }}</div>
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-top: 10px;">
        <div>
            <h1 style="margin: 0; font-size: 26px; line-height: 1.2; color: #180444;">
                {{ $greeting }},<br>
                <span>{{ $user->name }}</span>
            </h1>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px;">
                <span class="badge" style="background:#fed939; color:#180444; font-weight:700; font-size:11px; padding: 4px 10px; border-radius: 12px;">
                    @include('icon',['name'=>'users']) {{ $user->jobRole() }}
                </span>
                @if($user->phone)
                    <span class="badge" style="background:#efe7fc; color:#571e90; font-size:11px; padding: 4px 10px; border-radius: 12px;">
                        {{ $user->phone }}
                    </span>
                @endif
            </div>
        </div>
    </div>
    <div class="site-pill" style="margin-top: 14px;">
        @include('icon',['name'=>'pin']) {{ $siteName }}
    </div>
    @include('park-art')
</section>

@if(!$canPunch)
<section class="punch-card" data-attendance-unavailable>
    <div class="punch-card-top"><span class="staff-eyebrow">TODAY'S ATTENDANCE STATUS</span></div>
    <div class="location-box" role="status">
        <span class="location-icon">@include('icon',['name'=>'shield'])</span>
        <div>
            <strong>{{ $user->attendance_enabled ? 'No work site assigned' : 'Attendance is not enabled' }}</strong>
            <p>{{ $user->attendance_enabled ? 'Ask your administrator to assign you a work site before you can punch in.' : 'Attendance is not enabled for your account. Contact your administrator.' }}</p>
        </div>
    </div>
</section>
@else
<section class="punch-card" data-attendance
    data-punch-url="{{ route('staff.punch') }}"
    data-action="{{ $active ? 'out' : 'in' }}"
    data-has-active="{{ $active ? 'true' : 'false' }}"
    data-session-id="{{ $active?->id }}"
    data-server-now="{{ $now->toIso8601String() }}"
    data-started-at="{{ $active?->started_at->toIso8601String() }}"
    data-closed-seconds="{{ $closedSeconds }}"
    data-timezone="{{ $tz }}"
    data-accuracy-limit="{{ $active?->site_snapshot['max_accuracy_m'] ?? $user->workSite?->max_accuracy_m ?? 100 }}">

    <div class="punch-card-top">
        <span class="staff-eyebrow">TODAY'S ATTENDANCE STATUS</span>
        <span class="duty-badge {{ $active ? 'on' : '' }}">
            <i></i>
            @if($active)
                Punched In · On Duty
            @elseif($todayOut)
                Punched Out · Off Duty
            @else
                Not Punched In · Off Duty
            @endif
        </span>
    </div>

    <div class="punch-clock" data-clock>
        {{ $now->setTimezone($tz)->format('h:i:s') }} <small>{{ $now->setTimezone($tz)->format('A') }}</small>
    </div>
    <div class="clock-zone">{{ $tz }} (Asia/Kolkata) · Server-synchronised time</div>

    <div class="time-pair" style="margin: 20px 0 16px; background: #faf7fe; padding: 14px 10px; border-radius: 14px; border: 1px solid #e8ddf8;">
        <div>
            <span>Today's Punch-In</span>
            <strong style="color: #7a31b7; font-size: 15px;">{{ $todayIn ?? 'Not yet' }}</strong>
        </div>
        <div>
            <span>Today's Punch-Out</span>
            <strong style="color: #180444; font-size: 15px;">{{ $todayOut ?? ($active ? 'In progress' : 'Not yet') }}</strong>
        </div>
    </div>

    @if($active)
    <div class="shift-duration" style="background: #f5effd; border: 1px solid #decbf6; color: #571e90;">
        @include('icon',['name'=>'clock']) Current shift duration: <strong data-elapsed style="color: #7a31b7;">—</strong>
        <span>Started at {{ $active->localTime('started_at', 'h:i A') }}</span>
    </div>
    @endif

    <div class="location-box" data-location-box>
        <span class="location-icon">@include('icon',['name'=>'pin'])</span>
        <div>
            <strong data-location-title>GPS Location Ready</strong>
            <p data-location-detail>Fresh location is requested whenever you tap Punch In or Punch Out.</p>
            <a data-location-map hidden target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">View reported location ↗</a>
        </div>
        <button type="button" class="location-check" data-check-location aria-label="Test GPS Location">Check</button>
    </div>

    <div class="punch-feedback" role="status" aria-live="polite" data-punch-feedback hidden></div>

    <div class="punch-buttons-container" style="display: flex; flex-direction: column; gap: 12px; margin-top: 14px;">
        <button class="punch-button punch-in-action" type="button" data-punch-in data-punch @disabled($active) style="{{ $active ? 'opacity: 0.5; background: #ede7f8; color: #7a6d96; box-shadow: none;' : 'background: #7a31b7; color: white; box-shadow: 0 6px 17px #7a31b735;' }}">
            @include('icon',['name'=>'fingerprint'])
            <span>
                <strong>Punch In</strong>
                <small>{{ $active ? 'Shift already open (since '.$active->localTime('started_at').')' : 'Tap to record start of shift with GPS' }}</small>
            </span>
            @include('icon',['name'=>'arrow'])
        </button>

        <button class="punch-button punch-out punch-out-action" type="button" data-punch-out @disabled(!$active) style="{{ !$active ? 'opacity: 0.5; background: #ede7f8; color: #7a6d96; box-shadow: none;' : 'background: #571e90; color: white; box-shadow: 0 6px 17px #571e9030;' }}">
            @include('icon',['name'=>'logout'])
            <span>
                <strong>Punch Out</strong>
                <small>{{ $active ? 'Tap to record end of shift with fresh GPS' : 'Requires active punch-in first' }}</small>
            </span>
            @include('icon',['name'=>'arrow'])
        </button>
    </div>

    <details class="punch-note" style="margin-top: 14px;">
        <summary style="cursor: pointer; font-size: 11px; color: #7a6d96;">Add optional punch note</summary>
        <label class="sr-only" for="punch-note">Punch note</label>
        <textarea id="punch-note" data-punch-note maxlength="500" placeholder="Optional notes for your administrator..."></textarea>
    </details>

    <p class="punch-footnote" style="margin-top: 10px;">
        @include('icon',['name'=>'shield']) GPS coordinates captured only when you punch. No background tracking.
    </p>
</section>
@endif

<section class="staff-section" style="margin-top: 20px;">
    <a class="staff-support" href="{{ route('staff.history') }}" style="background: #f8f4fe; border: 1px solid #decbf6; margin-top: 0; padding: 14px 16px;">
        <span class="support-icon" style="background: #efe7fc; color: #7a31b7;">@include('icon',['name'=>'calendar'])</span>
        <span>
            <strong style="color: #180444; font-size: 13px;">View My Attendance History</strong>
            <small style="color: #6f618f; font-size: 11px;">Check your past days, shift timings, and working hours.</small>
        </span>
        @include('icon',['name'=>'chevron'])
    </a>
</section>

<section class="staff-section">
    <div class="staff-section-head">
        <h2>Today's Activity</h2>
        <a href="{{ route('staff.history') }}">Full history @include('icon',['name'=>'chevron'])</a>
    </div>

    @forelse($sessions as $session)
    <a class="timeline-row" href="{{ route('staff.detail', $session) }}">
        <span class="timeline-icon">@include('icon',['name' => $session->ended_at ? 'check' : 'clock'])</span>
        <div>
            <strong>{{ $session->ended_at ? 'Shift completed' : 'Shift currently in progress' }}</strong>
            <p>{{ $session->localTime('started_at') }} → {{ $session->localTime('ended_at') }} · {{ $session->site_name }}</p>
        </div>
        <span class="timeline-duration">{{ $session->duration() }}</span>
    </a>
    @empty
    <div class="empty-day">
        @include('icon',['name'=>'sun'])
        <div>
            <strong>No punches recorded today.</strong>
            <p>Tap <strong>Punch In</strong> above to record your attendance.</p>
        </div>
    </div>
    @endforelse
</section>

<p class="staff-disclaimer" style="margin-top: 24px;">
    Your administrator can monitor attendance times, GPS location, and accuracy. Ensure location permissions are enabled in your mobile browser.
</p>
@endsection
