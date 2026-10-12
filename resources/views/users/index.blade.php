@extends('layouts.app')
@section('title','Staff Management')
@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">PARK EMPLOYEES & WORKFORCE</div>
        <h1>Staff Management</h1>
        <p class="muted">Add and manage all employees working at the park, configure trade roles, mobile attendance access, and login credentials.</p>
    </div>
    <a class="btn" href="{{ route('users.create') }}">@include('icon',['name'=>'plus']) Add Employee</a>
</div>

<div class="stat-grid staff-stats">
    <div class="stat">
        <div class="stat-top">Total Employees<span class="stat-icon">@include('icon',['name'=>'users'])</span></div>
        <div class="stat-value">{{ $totalCount }}</div>
        <div class="stat-note">All park staff profiles</div>
    </div>
    <div class="stat">
        <div class="stat-top">Active Accounts<span class="stat-icon">@include('icon',['name'=>'check'])</span></div>
        <div class="stat-value">{{ $activeCount }}<span class="stat-dot"></span></div>
        <div class="stat-note">Can log in & punch attendance</div>
    </div>
    <div class="stat">
        <div class="stat-top">Inactive Accounts<span class="stat-icon">@include('icon',['name'=>'shield'])</span></div>
        <div class="stat-value">{{ $inactiveCount }}</div>
        <div class="stat-note">Access suspended</div>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Employee Directory</h2>
            <p class="muted small">Full profiles, contact info, job roles, and login statuses.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('users.index') }}" class="attendance-filters user-filters">
        <div class="field" style="flex: 2; min-width: 220px;">
            <label for="search-input">Search Employees</label>
            <input id="search-input" type="search" name="search" value="{{ $search }}" placeholder="Search by name, mobile, email, or trade...">
        </div>
        <div class="field">
            <label for="status-filter">Account Status</label>
            <select id="status-filter" name="status">
                <option value="all" @selected($status==='all')>All statuses</option>
                <option value="active" @selected($status==='active')>Active only</option>
                <option value="inactive" @selected($status==='inactive')>Inactive only</option>
            </select>
        </div>
        <div class="field">
            <label for="trade-filter">Trade / Job Role</label>
            <select id="trade-filter" name="trade">
                <option value="">All roles & trades</option>
                @foreach($allTrades as $t)
                    <option value="{{ $t }}" @selected($trade===$t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="role-filter">Access Level</label>
            <select id="role-filter" name="role">
                <option value="all" @selected($role==='all')>All access levels</option>
                <option value="staff" @selected($role==='staff')>Staff (Mobile only)</option>
                <option value="cashier" @selected($role==='cashier')>Cashier</option>
                <option value="admin" @selected($role==='admin')>Administrator</option>
            </select>
        </div>
        <div style="display:flex; gap:8px; align-items:flex-end;">
            <button class="btn secondary" type="submit">Filter</button>
            @if($search || $status !== 'all' || $trade || $role !== 'all')
                <a class="btn secondary" href="{{ route('users.index') }}">Reset</a>
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
                    <th>LOGIN EMAIL</th>
                    <th>ACCOUNT STATUS</th>
                    <th>ACCESS LEVEL</th>
                    <th style="text-align: right;">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                <tr>
                    <td>
                        <div class="person-cell">
                            <span class="person-avatar">{{ strtoupper(substr($u->name,0,1)) }}</span>
                            <div>
                                <strong>{{ $u->name }}</strong>
                                <small class="muted">{{ $u->employee_code ?: 'ID not assigned' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($u->phone)
                            <span style="font-family: monospace; font-size: 13px; font-weight: 500;">{{ $u->phone }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge" style="background: #fed939; color: #180444; font-weight: 700; border-radius: 14px; padding: 4px 10px;">
                            {{ $u->jobRole() }}
                        </span>
                    </td>
                    <td>
                        <span style="font-size: 13px;">{{ $u->email }}</span>
                    </td>
                    <td>
                        <span class="badge" style="{{ $u->active ? 'background:#efe7fc;color:#571e90;' : 'background:#f2edf9;color:#7a6d96;' }}">
                            {{ $u->active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $u->role==='admin'?'purple':($u->role==='cashier'?'orange':'') }}">
                            {{ ucfirst($u->role) }}
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end;">
                            <a class="btn secondary small" href="{{ route('users.edit', $u) }}" title="Edit details or reset password">
                                Edit / Reset
                            </a>
                            <form method="POST" action="{{ route('users.toggle-status', $u) }}" style="display: inline;" onsubmit="return confirm('Change status for {{ addslashes($u->name) }} to {{ $u->active ? 'Inactive' : 'Active' }}?');">
                                @csrf
                                <button type="submit" class="btn secondary small" style="min-width: 80px; {{ $u->active ? 'color: #b91c1c; border-color: #fecaca;' : 'color: #15803d; border-color: #bbf7d0;' }}">
                                    {{ $u->active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty">
                            @include('icon',['name'=>'users'])
                            <h3>No employees match your filter.</h3>
                            <p>Try clearing your search terms or add a new employee.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
