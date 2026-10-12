<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PointEntry extends Model {  protected $guarded=['id']; protected function casts(): array{return [];} public function sale(){return $this->belongsTo(Sale::class);} public function redemption(){return $this->belongsTo(Redemption::class);} }
