@extends('layouts.app')
@section('title','Attendance Dashboard')
@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">ATTENDANCE DASHBOARD · PARK OPERATIONS</div>
        <h1>Employee Attendance Register</h1>
        <p class="muted">Monitor live punches, GPS location evidence, timestamps (Asia/Kolkata), and completed shift durations across all staff.</p>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <a class="btn secondary" href="{{ route('crm.export', $filters) }}">@include('icon',['name'=>'download']) Export CSV</a>
        <a class="btn" href="{{ route('users.create') }}">@include('icon',['name'=>'plus']) Add Employee</a>
    </div>
</div>

<div class="stat-grid crm-stats">
    <div class="stat">
        <div class="stat-top">On Duty Now<span class="stat-icon">@include('icon',['name'=>'users'])</span></div>
        <div class="stat-value">{{ $active }}<span class="stat-dot"></span></div>
        <div class="stat-note">Currently open shifts with active punch-in</div>
    </div>
    <div class="stat">
        <div class="stat-top">Employees Recorded<span class="stat-icon">@include('icon',['name'=>'fingerprint'])</span></div>
        <div class="stat-value">{{ $people }}</div>
        <div class="stat-note">Distinct employees in filtered records</div>
    </div>
    <div class="stat">
        <div class="stat-top">Completed Working Duration<span class="stat-icon">@include('icon',['name'=>'clock'])</span></div>
        <div class="stat-value compact">{{ \App\Domain\AttendancePolicy::duration($seconds) }}</div>
        <div class="stat-note">Total hours for closed attendance sessions</div>
    </div>
    <div class="stat">
        <div class="stat-top">Flagged for Review<span class="stat-icon">@include('icon',['name'=>'alert'])</span></div>
        <div class="stat-value">{{ $review }}</div>
        <div class="stat-note">Shifts flagged for admin inspection</div>
    </div>
</div>

@if($pending)
<div class="review-strip" style="margin-bottom: 20px;">
    <span>@include('icon',['name'=>'receipt']) <strong>{{ $pending }} pending correction request(s)</strong> awaiting review.</span>
    <a href="{{ route('crm.requests') }}">Review requests →</a>
</div>
@endif

<section class="panel attendance-panel">
    <div class="panel-head">
        <div>
            <h2>Attendance Records & Location Log</h2>
            <p class="muted small">Filter by employee name, mobile number, trade role, date, and attendance status.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('attendance.index') }}" class="attendance-filters" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
        <div class="field" style="flex: 2; min-width: 220px;">
            <label for="search">Search Employee or Mobile</label>
            <input id="search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, phone number, email...">
        </div>
        <div class="field" style="min-width: 140px;">
            <label for="from">From Date</label>
            <input id="from" type="date" name="from" value="{{ $filters['from'] }}" required>
        </div>
        <div class="field" style="min-width: 140px;">
            <label for="to">To Date</label>
            <input id="to" type="date" name="to" value="{{ $filters['to'] }}" required>
        </div>
        <div class="field" style="min-width: 160px;">
            <label for="staff-filter">Employee</label>
            <select id="staff-filter" name="user_id">
                <option value="">All employees</option>
                @foreach($staff as $u)
                    <option value="{{ $u->id }}" @selected((string)($filters['user_id']??'')===(string)$u->id)>
                        {{ $u->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field" style="min-width: 160px;">
            <label for="trade-filter">Trade / Job Role</label>
            <select id="trade-filter" name="trade">
                <option value="">All trades & roles</option>
                @foreach($trades as $t)
                    <option value="{{ $t }}" @selected(($filters['trade']??'')===$t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="min-width: 140px;">
            <label for="status-filter">Status</label>
            <select id="status-filter" name="status">
                <option value="all" @selected(($filters['status']??'all')==='all')>All sessions</option>
                <option value="open" @selected(($filters['status']??'')==='open')>On duty (Open)</option>
                <option value="closed" @selected(($filters['status']??'')==='closed')>Completed (Closed)</option>
                <option value="review" @selected(($filters['status']??'')==='review')>Needs review</option>
            </select>
        </div>
        <div style="display:flex; gap:8px;">
            <button class="btn secondary" type="submit">Filter</button>
            @if(!empty($filters['search']) || !empty($filters['trade']) || !empty($filters['user_id']) || ($filters['status']??'all')!=='all' || $filters['from'] !== now()->toDateString() || $filters['to'] !== now()->toDateString())
                <a class="btn secondary" href="{{ route('attendance.index') }}">Reset</a>
            @endif
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>EMPLOYEE NAME</th>
                    <th>MOBILE NUMBER</th>
                    <th>TRADE / JOB ROLE</th>
                    <th>DATE</th>
                    <th>PUNCH-IN TIME</th>
                    <th>PUNCH-IN LOCATION</th>
                    <th>PUNCH-OUT TIME</th>
                    <th>PUNCH-OUT LOCATION</th>
                    <th>STATUS</th>
                    <th>WORKING DURATION</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                @php
                    $inPunch = $row->inPunch();
                    $outPunch = $row->outPunch();
                @endphp
                <tr>
                    <td>
                        <div class="person-cell">
                            <span class="person-avatar">{{ strtoupper(substr($row->user->name, 0, 1)) }}</span>
                            <div>
                                <strong>{{ $row->user->name }}</strong>
                                <small class="muted">{{ $row->user->employee_code ?: 'EMP-'.$row->user->id }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($row->user->phone)
                            <span style="font-family: monospace; font-size: 13px;">{{ $row->user->phone }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge blue" style="background: #e0f2fe; color: #0369a1; font-weight: 600;">
                            {{ $row->user->jobRole() }}
                        </span>
                    </td>
                    <td>
                        <strong>{{ $row->work_date }}</strong>
                    </td>
                    <td>
                        <strong style="color: #15803d;">{{ $row->localTime('started_at', 'h:i:s A') }}</strong>
                    </td>
                    <td>
                        @if($inPunch)
                            <div style="display: flex; flex-direction: column; gap: 3px;">
                                <span style="font-family: monospace; font-size: 11px; color: #334155;">
                                    {{ number_format($inPunch->latitude, 5) }}, {{ number_format($inPunch->longitude, 5) }}
                                </span>
                                <small class="muted" style="font-size: 10px;">±{{ round($inPunch->accuracy_m) }}m accuracy</small>
                                <a href="{{ $inPunch->mapUrl() }}" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: #2563eb; text-decoration: underline;" title="Open in Google Maps">
                                    @include('icon',['name'=>'pin']) View Location ↗
                                </a>
                            </div>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($row->ended_at)
                            <strong style="color: #475569;">{{ $row->localTime('ended_at', 'h:i:s A') }}</strong>
                            @if($row->localTime('ended_at','Y-m-d') !== $row->work_date)
                                <div class="muted small">{{ $row->localTime('ended_at','d M') }}</div>
                            @endif
                        @else
                            <span class="badge blue">In progress</span>
                        @endif
                    </td>
                    <td>
                        @if($outPunch)
                            <div style="display: flex; flex-direction: column; gap: 3px;">
                                <span style="font-family: monospace; font-size: 11px; color: #334155;">
                                    {{ number_format($outPunch->latitude, 5) }}, {{ number_format($outPunch->longitude, 5) }}
                                </span>
                                <small class="muted" style="font-size: 10px;">±{{ round($outPunch->accuracy_m) }}m accuracy</small>
                                <a href="{{ $outPunch->mapUrl() }}" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: #2563eb; text-decoration: underline;" title="Open in Google Maps">
                                    @include('icon',['name'=>'pin']) View Location ↗
                                </a>
                            </div>
                        @elseif($row->ended_at)
                            <span class="muted" style="font-size: 11px;">Manual correction</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($row->needs_review)
                            <span class="badge orange">Needs review</span>
                        @elseif($row->ended_at)
                            <span class="badge gray">Completed</span>
                        @else
                            <span class="badge green" style="background:#dcfce7;color:#15803d;">On duty</span>
                        @endif
                    </td>
                    <td>
                        <strong style="font-size: 13px; color: #1e293b;">{{ $row->workingDuration() }}</strong>
                        @if(!$row->ended_at)
                            <small class="muted" style="display: block; font-size: 10px;">(live elapsed)</small>
                        @endif
                    </td>
                    <td>
                        <a class="table-link" href="{{ route('crm.detail', $row) }}">Details →</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11">
                        <div class="empty">
                            @include('icon',['name'=>'users'])
                            <h3>No attendance records in this selection.</h3>
                            <p>Adjust your date range or search filters to find records.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rows->links() }}

    <p class="notice" style="margin-top: 16px;">
        All timestamps are server-recorded and displayed in the park's local timezone (<strong>Asia/Kolkata</strong>). Punch-in and punch-out locations are captured in real-time from device GPS sensors.
    </p>
</section>
@endsection
