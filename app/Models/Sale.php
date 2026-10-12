<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model {  protected $guarded=['id']; protected function casts(): array{return ['receipt_settings'=>'array','voided_at'=>'datetime'];} public function pass(){return $this->belongsTo(ParkPass::class,'park_pass_id');} public function items(){return $this->hasMany(SaleItem::class);} public function user(){return $this->belongsTo(User::class);} }
