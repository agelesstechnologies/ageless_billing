<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Redemption extends Model {  protected $guarded=['id']; protected function casts(): array{return ['authorized_at'=>'datetime','acknowledged_at'=>'datetime'];} public function machine(){return $this->belongsTo(Machine::class);} public function pass(){return $this->belongsTo(ParkPass::class,'park_pass_id');} public function ride(){return $this->belongsTo(Ride::class)->withTrashed();} }
