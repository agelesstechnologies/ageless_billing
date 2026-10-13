@extends('layouts.app')
@section('title','Billing')
@section('body-class','pos-page')
@push('head')<link rel="stylesheet" href="{{ asset('assets/pos.css') }}">@endpush
@section('content')
@php
$catalog=$packages->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'description'=>$p->description,'kind'=>$p->kind,'price'=>$p->price_paise,'points'=>$p->points,'hours'=>$p->validity_hours])->values();
@endphp
<form id="pos-form" method="POST" action="{{ route('sales.store') }}" data-catalog="{{ $catalog->toJson() }}" data-old-items="{{ json_encode(old('items',[])) }}" data-tax-bps="{{ \App\Domain\Money::paise($park['tax_rate']) }}" data-tax-mode="{{ $park['tax_mode'] }}" data-admin="{{ auth()->user()->role==='admin'?'1':'0' }}">@csrf<input type="hidden" name="request_id" value="{{ old('request_id',$requestId) }}">
<div class="pos-layout"><section class="pos-left">
<div class="catalog-toolbar"><div class="search">@include('icon',['name'=>'search'])<input type="search" id="catalog-search" placeholder="Search ticket or package..." aria-label="Search tickets and packages"><kbd>F1</kbd></div><div class="tabs" role="group" aria-label="Category"><button type="button" class="tab active" data-filter="all">All</button><button type="button" class="tab" data-filter="ticket">Tickets</button><button type="button" class="tab" data-filter="package">Packages</button></div><span id="catalog-count" class="muted">{{ $packages->count() }} options</span></div>
<div class="catalog-grid">
@forelse($packages as $package)<button type="button" class="package-card" data-add="{{ $package->id }}" data-card-id="{{ $package->id }}" data-kind="{{ $package->kind }}" data-search="{{ strtolower($package->name.' '.$package->description) }}" style="--card-color:{{ $package->color }}" aria-label="Add {{ $package->name }}"><span class="package-art"><span class="package-icon">@include('icon',['name'=>$package->icon])</span><span class="kind">{{ $package->kind }}</span></span><span class="package-info"><strong>{{ $package->name }}</strong><span class="package-price">₹{{ \App\Domain\Money::display($package->price_paise) }}</span></span></button>@empty<div class="empty"><h3>No active tickets yet.</h3><p>An administrator can add tickets under Tickets & packages.</p></div>@endforelse
</div>
<div class="recent panel"><div class="row between"><h3>Recent Tickets</h3><a href="{{ route('sales.index') }}" class="text-link">View All ›</a></div><table><thead><tr><th>#</th><th>Time</th><th>Ticket No</th><th>Items</th><th class="num">Amount (₹)</th><th>Payment</th></tr></thead><tbody>
@forelse($recent as $i=>$sale)<tr><td>{{ $i+1 }}</td><td>{{ $sale->created_at->format('h:i A') }}</td><td><a href="{{ route('sales.show',$sale) }}">{{ $sale->number }}</a></td><td>{{ $sale->items->map(fn($it)=>$it->name.' ×'.$it->quantity)->implode(', ') }}</td><td class="num">{{ \App\Domain\Money::display($sale->total_paise) }}</td><td><span class="pay-chip {{ $sale->payment_method }}">{{ ucfirst($sale->payment_method) }}</span></td></tr>@empty<tr><td colspan="6" class="empty">No tickets issued yet.</td></tr>@endforelse
</tbody></table></div>
</section>
<aside class="checkout">
<div class="checkout-head"><h2>Ticket details</h2><button type="button" class="clear-btn" id="clear-cart">Clear all</button></div>
<span hidden id="item-count"></span><div id="cart" class="cart"></div><div id="cart-fields"></div>
<div class="totals"><div class="total-row"><span>Subtotal</span><span id="subtotal">₹0.00</span></div>
@if(auth()->user()->role==='admin')<div class="total-row"><label for="discount">Discount (₹)</label><input name="discount" id="discount" type="number" min="0" step="0.01" value="{{ old('discount','0') }}"></div>@else<input type="hidden" name="discount" id="discount" value="0">@endif
<span id="tax" hidden>?0.00</span>
<div class="total-row"><span>CGST ({{ rtrim(rtrim(number_format((float)$park['tax_rate']/2,2,'.',''),'0'),'.') }}%)</span><span id="cgst">?0.00</span></div>
<div class="total-row"><span>SGST ({{ rtrim(rtrim(number_format((float)$park['tax_rate']/2,2,'.',''),'0'),'.') }}%)</span><span id="sgst">?0.00</span></div>
<div class="total-row grand"><span>Total Amount</span><span id="total">₹0.00</span></div><p class="small muted" id="validity-note"></p><span hidden id="points-total"></span></div>
<h3 class="section-title">Payment Method</h3><div class="payment-options">@foreach(['cash'=>'Cash','card'=>'Card','upi'=>'UPI'] as $key=>$label)<label class="payment-option"><input type="radio" name="payment_method" value="{{ $key }}" @checked(old('payment_method','cash')===$key)><span>@include('icon',['name'=>['cash'=>'money','card'=>'receipt','upi'=>'qr'][$key]]) {{ $label }}</span></label>@endforeach</div>
<div class="field" id="cash-field"><label for="received">Amount Received</label><div class="received-row"><input name="received" id="received" type="number" min="0" step="0.01" placeholder="Exact amount" value="{{ old('received') }}"><div id="quick-amounts" class="quick-amounts"></div></div><div class="change-row"><span>Change</span><strong id="change-note">₹0.00</strong></div></div>
<div class="field" id="reference-field" hidden><label for="payment_reference">Payment reference · optional</label><input id="payment_reference" name="payment_reference" maxlength="80" value="{{ old('payment_reference') }}"></div>
<p id="checkout-error" class="small" style="color:#b74934" role="alert"></p>
<button type="submit" class="generate-btn" id="checkout-button" disabled>@include('icon',['name'=>'qr']) Generate Ticket (F2)</button><p class="foot-note">Record payment only after collecting it. No gateway is connected.</p></aside></div><noscript><div class="alert error">Enable JavaScript to use billing.</div></noscript></form>
@endsection
