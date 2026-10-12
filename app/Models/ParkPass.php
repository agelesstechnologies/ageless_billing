<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ParkPass extends Model {  protected $guarded=['id']; protected function casts(): array{return ['expires_at'=>'datetime','balance'=>'integer'];} public function sales(){return $this->hasMany(Sale::class);} public function entries(){return $this->hasMany(PointEntry::class);} public function getUsableAttribute(){return $this->status==='active' && $this->expires_at->isFuture();} }
