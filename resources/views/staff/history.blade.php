@extends('layouts.staff')
@section('title','My attendance history')
@section('content')
<div class="staff-page-title"><span class="staff-eyebrow">YOUR PARK DAYS</span><h1>Attendance history</h1><p>Your punches, locations and recorded time.</p></div>
<form method="GET" class="staff-filter"><label for="month">Month</label><input id="month" type="month" name="month" value="{{ $month }}" required><button class="btn small">Show</button></form>
<p class="staff-disclaimer">Each shift is listed under its punch-in date at that site. Time shown is recorded attendance, not payroll or paid hours.</p>
@forelse($sessions as $s)<a class="history-card" href="{{ route('staff.detail',$s) }}"><div class="history-top"><div><strong>{{ \Carbon\CarbonImmutable::parse($s->work_date)->format('d M, l') }}</strong><small>{{ $s->site_name }}</small></div><span class="badge {{ $s->needs_review?'orange':($s->ended_at?'gray':'') }}">{{ $s->needs_review?'Needs review':($s->ended_at?'Completed':'On duty') }}</span></div><div class="history-times"><div><span>IN</span><b>{{ $s->localTime('started_at') }}</b></div><div><span>OUT</span><b>{{ $s->localTime('ended_at') }}</b></div><div><span>DURATION</span><b>{{ $s->duration() }}</b></div></div><div class="history-bottom"><span>@include('icon',['name'=>'pin']) {{ $s->punches->count() }} recorded location(s)</span><span>Details @include('icon',['name'=>'chevron'])</span></div></a>@empty<div class="staff-card empty"><h3>No punches this month.</h3><p>Change the month or send a request for a missed day.</p></div>@endforelse
{{ $sessions->links() }}
@endsection
