@extends('layouts.app')
@section('title','Audit trail')
@section('content')
<div class="page-head"><div><div class="eyebrow">ACCOUNTABILITY, BUILT IN</div><h1>Audit trail</h1><p class="muted">Administrator changes and billing events. Records are not editable through the interface.</p></div></div><div class="panel"><div class="table-wrap"><table><thead><tr><th>WHEN</th><th>STAFF ID</th><th>ACTION</th><th>RECORD</th><th>DETAILS</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at->format('d M Y, h:i:s A') }}</td><td>{{ $log->user_id ?? 'CLI / system' }}</td><td>{{ $log->action }}</td><td>{{ $log->entity_type }} #{{ $log->entity_id }}</td><td class="small">{{ json_encode($log->details,JSON_UNESCAPED_UNICODE) }}</td></tr>@empty<tr><td class="empty" colspan="5">No audit entries yet.</td></tr>@endforelse</tbody></table></div>{{ $logs->links() }}</div>
@endsection
