@extends('layouts.app')
@section('title', $staff->exists ? 'Edit Employee: '.$staff->name : 'Add New Employee')
@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">STAFF MANAGEMENT</div>
        <h1>{{ $staff->exists ? 'Edit Employee Profile' : 'Add New Employee' }}</h1>
        <p class="muted">{{ $staff->exists ? 'Update contact details, trade role, login credentials, or account status.' : 'Register a new employee for park operations and mobile location-based attendance.' }}</p>
    </div>
    <a class="btn secondary" href="{{ route('users.index') }}">← Back to Employee Directory</a>
</div>

<div class="panel form-shell">
    <form method="POST" action="{{ $staff->exists ? route('users.update', $staff) : route('users.store') }}">
        @csrf
        @if($staff->exists)
            @method('PUT')
        @endif

        <div style="margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">Employee Information</h2>
            <p class="muted small">Personal and operational details for park identification.</p>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="name">Full Name <span style="color:#e11d48">*</span></label>
                <input id="name" name="name" required value="{{ old('name', $staff->name) }}" maxlength="100" placeholder="e.g. Ramesh Kumar">
                <p class="hint">Employee's official name displayed on dashboard and reports.</p>
            </div>

            <div class="field">
                <label for="phone">Mobile Number <span style="color:#e11d48">*</span></label>
                <input id="phone" name="phone" type="tel" required maxlength="24" value="{{ old('phone', $staff->phone) }}" placeholder="e.g. +91 98765 43210">
                <p class="hint">Contact mobile number for administrative and emergency reach.</p>
            </div>

            <div class="field">
                <label for="trade">Trade / Job Role / Type of Work <span style="color:#e11d48">*</span></label>
                <input id="trade" name="trade" list="trade-options" required maxlength="100" value="{{ old('trade', $staff->trade ?: $staff->designation) }}" placeholder="e.g. Ride Operator, Technician, Electrician...">
                <datalist id="trade-options">
                    @foreach($trades as $t)
                        <option value="{{ $t }}">
                    @endforeach
                </datalist>
                <p class="hint">Type of work performed at the park (e.g. Ride operator, technician, electrician, housekeeping, security).</p>
            </div>

            <div class="field">
                <label for="employee-code">Employee Badge / ID (Optional)</label>
                <input id="employee-code" name="employee_code" value="{{ old('employee_code', $staff->employee_code) }}" maxlength="32" placeholder="e.g. EMP-101">
                <p class="hint">Unique park badge code if applicable.</p>
            </div>
        </div>

        <div style="margin: 32px 0 20px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">Login Credentials & Authentication</h2>
            <p class="muted small">Employees will use these credentials to log in to the mobile attendance web app.</p>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="email">Email Address (Login Username) <span style="color:#e11d48">*</span></label>
                <input id="email" name="email" required type="email" value="{{ old('email', $staff->email) }}" placeholder="e.g. ramesh@agelesspark.com">
                <p class="hint">Each employee must have a unique email address used for signing in.</p>
            </div>

            <div class="field">
                <label for="role">System Access Level <span style="color:#e11d48">*</span></label>
                <select id="role" name="role" required>
                    <option value="staff" @selected(old('role', $staff->role) === 'staff')>Staff · Mobile Attendance Only</option>
                    <option value="cashier" @selected(old('role', $staff->role) === 'cashier')>Cashier · Ticket POS + Attendance</option>
                    <option value="admin" @selected(old('role', $staff->role) === 'admin')>Administrator · Full Park Management</option>
                </select>
                <p class="hint">Staff accounts are restricted to the mobile attendance portal.</p>
            </div>

            <div class="field">
                <label for="password">{{ $staff->exists ? 'Reset Password (Leave blank to keep unchanged)' : 'Password' }} @if(!$staff->exists)<span style="color:#e11d48">*</span>@endif</label>
                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" @required(!$staff->exists) placeholder="{{ $staff->exists ? 'Enter new password to reset' : 'Create login password' }}">
                <p class="hint">Securely hashed before storing. Minimum 8 characters.</p>
            </div>

            <div class="field">
                <label for="password-confirmation">Confirm Password @if(!$staff->exists)<span style="color:#e11d48">*</span>@endif</label>
                <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!$staff->exists) placeholder="Re-type password">
                <p class="hint">Must match password above.</p>
            </div>
        </div>

        <div style="margin: 32px 0 20px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">
            <h2 style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">Account Status & Attendance Settings</h2>
            <p class="muted small">Control account activation and location attendance permissions.</p>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="work-site">Assigned Work Site</label>
                <select id="work-site" name="work_site_id">
                    <option value="">— No site assigned —</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" @selected((string)old('work_site_id', $staff->work_site_id) === (string)$site->id)>
                            {{ $site->name }} ({{ $site->timezone }})
                        </option>
                    @endforeach
                </select>
                <p class="hint">Staff cannot punch in until a site is assigned.</p>
            </div>

            <div class="field" style="display: flex; flex-direction: column; justify-content: center; gap: 12px; padding-top: 10px;">
                <label class="check" style="font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="hidden" name="active" value="0">
                    <input name="active" type="checkbox" value="1" @checked(old('active', $staff->exists ? $staff->active : true)) style="width: 18px; height: 18px;">
                    Account Status: Active (Allow Login)
                </label>
                <p class="hint" style="margin-left: 26px;">When unchecked (Inactive), employee cannot sign in or punch attendance.</p>

                <label class="check" style="font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="hidden" name="attendance_enabled" value="0">
                    <input name="attendance_enabled" type="checkbox" value="1" @checked(old('attendance_enabled', $staff->exists ? $staff->attendance_enabled : true)) style="width: 18px; height: 18px;">
                    Enable Location-Based Attendance
                </label>
                <p class="hint" style="margin-left: 26px;">Enables Punch In and Punch Out with browser geolocation.</p>
            </div>
        </div>

        <div class="alert" style="margin-top: 24px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;">
            <strong>Mobile Access Instructions:</strong> Share the park link <code>{{ url('/login') }}</code> and these login credentials with the employee. Once logged in from their phone, they can immediately tap <strong>Punch In</strong> and <strong>Punch Out</strong> using their current location.
        </div>

        <div class="form-actions" style="margin-top: 28px; display: flex; gap: 12px; justify-content: flex-end;">
            <a class="btn secondary" href="{{ route('users.index') }}">Cancel</a>
            <button class="btn" type="submit">{{ $staff->exists ? 'Save Employee Profile' : 'Create Employee Profile' }}</button>
        </div>
    </form>
</div>
@endsection
