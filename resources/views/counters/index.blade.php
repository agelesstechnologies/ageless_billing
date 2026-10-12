@extends('layouts.app')
@section('title','Ticket counters')
@section('content')
<div class="page-head"><div><div class="eyebrow">TICKET COUNTERS</div><h1>Ticket counters</h1><p class="muted">Each counter signs in with its own ID and password, issues tickets and sees its billing.</p></div><a class="btn" href="{{ route('counters.create') }}">@include('icon',['name'=>'plus']) Add ticket counter</a></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>COUNTER</th><th>LOGIN ID</th><th>STATUS</th><th>BILLS</th><th>TODAY</th><th>ALL-TIME</th><th></th></tr></thead><tbody>
@forelse($counters as $c)@php($s=$stats->get($c->id))<tr><td>{{ $c->name }}@if($c->phone)<p class="small muted">{{ $c->phone }}</p>@endif</td><td><code>{{ $c->employee_code }}</code></td><td><span class="badge {{ $c->active?'':'red' }}">{{ $c->active?'Active':'Inactive' }}</span></td><td>{{ (int)($s->bills??0) }}</td><td class="nowrap">₹{{ \App\Domain\Money::display((int)($s->today_total??0)) }}</td><td class="nowrap">₹{{ \App\Domain\Money::display((int)($s->total??0)) }}</td><td class="nowrap"><a class="table-link" href="{{ route('sales.index',['counter'=>$c->id]) }}">View billing</a> · <a class="table-link" href="{{ route('users.edit',$c) }}">Edit</a></td></tr>
@empty<tr><td colspan="7" class="empty">No ticket counters yet. Add one to get started.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
