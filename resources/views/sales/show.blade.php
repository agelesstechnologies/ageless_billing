@extends('layouts.app')
@section('title','Bill '.$sale->number)
@php
$printWidth=(string)$sale->receipt_settings['receipt_width']==='80'?'82':$sale->receipt_settings['receipt_width'];
@endphp
@section('body-class','receipt-width-'.$printWidth)
@push('head')<link rel="stylesheet" href="{{ asset('assets/receipt.css') }}"><style>@page{size:{{ $printWidth==='A4'?'A4 landscape':$printWidth.'mm 297mm' }};margin:{{ $printWidth==='A4'?'12mm':'3mm' }};}</style>@endpush
@section('content')
@php
$s=$sale->receipt_settings;
$cgst=intdiv($sale->tax_paise+1,2);
$sgst=$sale->tax_paise-$cgst;
$taxableSubtotal=$s['tax_mode']==='inclusive'?$sale->subtotal_paise-$sale->tax_paise:$sale->subtotal_paise;
$halfTaxRate=rtrim(rtrim(number_format((float)$s['tax_rate']/2,2,'.',''),'0'),'.');
@endphp
<div class="page-head"><div><div class="eyebrow">A LITTLE TICKET. A BIG ADVENTURE.</div><h1>Your QR bill is ready.</h1><p class="muted">{{ $sale->number }} · {{ $sale->kind==='topup'?'Existing pass recharged':'New QR pass issued' }}</p></div><a class="btn secondary" href="{{ route('pos') }}">New bill →</a></div>
<div class="receipt-layout"><article class="receipt"><header class="receipt-head">@if($s['logo'])<img class="receipt-logo" src="{{ asset('storage/'.$s['logo']) }}" alt="{{ $s['park_name'] }}">@endif<h2>{{ $s['park_name'] }}</h2><p>{{ $s['tagline'] }}</p><p style="white-space:pre-line">{{ $s['address'] }}</p>@if($s['phone'])<p>Phone: {{ $s['phone'] }}</p>@endif @if($s['tax_id'])<p>Tax ID: {{ $s['tax_id'] }}</p>@endif</header>
<div class="receipt-meta">
<h3 class="receipt-status">{{ $sale->status==='voided'?'VOIDED BILL':'PAID BILL' }}</h3>
<div><span>Bill No.</span><span>{{ $sale->number }}</span></div>
<div><span>Date</span><span>{{ $sale->created_at->format('d M Y, h:i A') }}</span></div>
<div><span>Guest</span><span>{{ $s['customer_name'] ?: 'Walk-in guest' }}</span></div>
@if($s['customer_phone'])<div><span>Phone</span><span>{{ $s['customer_phone'] }}</span></div>@endif
<div><span>Cashier</span><span>{{ $s['cashier_name'] }}</span></div>
</div>
<section class="receipt-items" aria-label="Bill items"><h3>ITEM</h3>
@foreach($sale->items as $item)
<div class="receipt-item"><strong>{{ $item->name }}</strong><div class="receipt-line"><span>{{ $item->quantity }} x Rs. {{ \App\Domain\Money::display($item->unit_price_paise) }}</span><span>Rs. {{ \App\Domain\Money::display($item->unit_price_paise*$item->quantity) }}</span></div><p>{{ $item->unit_points*$item->quantity }} points</p></div>
@endforeach
</section>
<section class="receipt-totals" aria-label="Bill totals">
<div class="receipt-line"><span>Subtotal</span><span>Rs. {{ \App\Domain\Money::display($taxableSubtotal) }}</span></div>
@if($sale->discount_paise)<div class="receipt-line"><span>Discount</span><span>?Rs. {{ \App\Domain\Money::display($sale->discount_paise) }}</span></div>@endif
<div class="receipt-line"><span>CGST ({{ $halfTaxRate }}%)</span><span>Rs. {{ \App\Domain\Money::display($cgst) }}</span></div>
<div class="receipt-line"><span>SGST ({{ $halfTaxRate }}%)</span><span>Rs. {{ \App\Domain\Money::display($sgst) }}</span></div>

<div class="receipt-line receipt-grand"><strong>TOTAL</strong><strong>Rs. {{ \App\Domain\Money::display($sale->total_paise) }}</strong></div>
</section>
<section class="receipt-payment" aria-label="Payment details">
<div class="receipt-line"><span>Payment</span><span>{{ strtoupper($sale->payment_method) }}</span></div>
@if($sale->payment_reference)<div class="receipt-line"><span>Reference</span><span>{{ $sale->payment_reference }}</span></div>@endif
@if($sale->payment_method==='cash')
<div class="receipt-line"><span>Received</span><span>Rs. {{ \App\Domain\Money::display($sale->received_paise) }}</span></div>
<div class="receipt-line"><span>Change</span><span>Rs. {{ \App\Domain\Money::display($sale->received_paise-$sale->total_paise) }}</span></div>
@endif
</section>
@if($sale->status==='paid')
<div class="receipt-redemption"><div class="receipt-qr">{!! $qr !!}</div><p class="receipt-note"><strong>{{ $sale->points_added }} points added on this bill</strong><span>Scan this QR at a ride to redeem points.</span></p></div>
@else<p class="receipt-note"><strong>This bill has been voided. Its pass is blocked.</strong></p>@endif
<footer class="receipt-footer"><p>Keep this QR safe. It is not a payment QR.</p><p>{{ $s['receipt_footer'] }}</p></footer></article>
<aside class="receipt-actions stack"><section class="panel"><div class="panel-head"><h2>Ready for the next adventure?</h2></div><button class="btn full" type="button" data-print>@include('icon',['name'=>'print']) Print {{ $printWidth==='A4'?'A4 bill':$printWidth.' mm receipt' }}</button><p class="notice">Choose the matching paper width in your printer settings. Disable browser headers and footers. A4 “Save as PDF” is also available through the browser.</p><a class="btn secondary full" href="{{ route('passes.show',$sale->pass) }}">Open QR wallet & history</a></section><section class="panel"><div class="tiny-label muted">CURRENT SERVER BALANCE</div><div class="pass-balance">{{ number_format($sale->pass->balance) }} <span class="small muted">pts</span></div><p class="notice">This live balance is separate from the original bill. It can change as the guest scans at rides.</p><p class="small">Pass expiry now: {{ $sale->pass->expires_at->format('d M Y, h:i A') }}</p></section>
@if(auth()->user()->role==='admin' && $sale->status==='paid')<section class="panel"><details><summary>Void an unused bill</summary><p class="notice">Only an untouched first-sale pass is eligible. This does not automatically refund cash, UPI or card payments.</p><form method="POST" action="{{ route('sales.void',$sale) }}" data-confirm="Void this unused bill and block its QR? Handle any payment refund separately.">@csrf<div class="field"><label>Reason</label><input name="reason" required minlength="5" maxlength="255"></div><button class="btn danger small">Void bill</button></form></details></section>@endif
@if($sale->status==='voided')<section class="panel"><h3>Voided</h3><p class="notice">{{ $sale->void_reason }}</p><p class="small muted">{{ $sale->voided_at?->format('d M Y, h:i A') }}</p></section>@endif</aside></div>
@endsection
