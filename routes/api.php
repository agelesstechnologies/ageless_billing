<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MachineApiController as Api;
Route::middleware(['throttle:machine','machine'])->group(function(){
 Route::get('/machine/config',[Api::class,'config']);
 Route::post('/machine/check',[Api::class,'check']);
 Route::get('/machine/check',[Api::class,'check']);
 Route::post('/machine/redeem',[Api::class,'redeem']);
 Route::get('/machine/transactions/{requestId}',[Api::class,'status']);
 Route::post('/machine/transactions/{requestId}/ack',[Api::class,'ack']);
 Route::post('/qr/redeem',[Api::class,'redeem']);
 Route::post('/qr/check',[Api::class,'check']);
});
