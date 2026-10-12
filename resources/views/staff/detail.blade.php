@extends('layouts.staff')
@section('title','Punch details')
@section('content')
<a href="{{ route('staff.history') }}" class="back-link">← My attendance</a><div class="staff-page-title"><span class="staff-eyebrow">{{ $attendance->site_name }}</span><h1>{{ \Carbon\CarbonImmutable::parse($attendance->work_date)->format('d M Y') }}</h1><p>Session #{{ $attendance->id }} · {{ $attendance->timezone }}</p></div>
<div class="staff-card"><div class="history-times"><div><span>EFFECTIVE IN</span><b>{{ $attendance->localTime('started_at','d M, h:i A') }}</b></div><div><span>EFFECTIVE OUT</span><b>{{ $attendance->localTime('ended_at','d M, h:i A') }}</b></div></div><div class="staff-total"><span>Recorded duration</span><strong>{{ $attendance->duration() }}</strong></div>@if($attendance->needs_review)<p class="alert warning">{{ $attendance->review_note }}</p>@endif</div>
@include('attendance.evidence')
<a class="btn secondary full" href="{{ route('staff.requests',['session'=>$attendance->id]) }}">Request a correction</a>
@endsection
