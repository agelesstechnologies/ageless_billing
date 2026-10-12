@extends('layouts.app')
@section('title','Add ticket counter')
@section('content')
<div class="page-head"><div><div class="eyebrow">TICKET COUNTERS</div><h1>Add ticket counter</h1><p class="muted">Create a counter login. The counter can issue tickets manually and see its billing.</p></div><a class="btn secondary" href="{{ route('counters.index') }}">← Back to counters</a></div>
<div class="panel form-shell"><form method="POST" action="{{ route('counters.store') }}">@csrf
<div class="field-grid">
<div class="field"><label for="name">Counter name <span style="color:#e11d48">*</span></label><input id="name" name="name" required maxlength="100" value="{{ old('name') }}" placeholder="e.g. Main Gate Counter 1"></div>
<div class="field"><label for="employee_code">Login ID <span style="color:#e11d48">*</span></label><input id="employee_code" name="employee_code" required maxlength="32" value="{{ old('employee_code') }}" placeholder="e.g. COUNTER-1" autocomplete="off" autocapitalize="none"><p class="hint">Letters, numbers and . _ - @ + only, no spaces. Used to sign in.</p></div>
<div class="field"><label for="password">Password <span style="color:#e11d48">*</span></label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="Minimum 8 characters"></div>
<div class="field"><label for="password-confirmation">Confirm password <span style="color:#e11d48">*</span></label><input id="password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
<div class="field"><label for="phone">Phone <span class="muted">· optional</span></label><input id="phone" name="phone" type="tel" maxlength="24" value="{{ old('phone') }}"></div>
<div class="field"><label for="email">Email <span class="muted">· optional</span></label><input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="counter@yourpark.com"><p class="hint">Can also be used to sign in.</p></div>
</div>
<div class="form-actions" style="margin-top:28px;display:flex;gap:12px;justify-content:flex-end"><a class="btn secondary" href="{{ route('counters.index') }}">Cancel</a><button class="btn" type="submit">Create ticket counter</button></div>
</form></div>
@endsection
