<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Machine extends Model { protected $hidden=['token_hash']; protected $guarded=['id']; protected function casts(): array{return ['enabled'=>'boolean','last_seen_at'=>'datetime'];} public function ride(){return $this->belongsTo(Ride::class)->withTrashed();} }
