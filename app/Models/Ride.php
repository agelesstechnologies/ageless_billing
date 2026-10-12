<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Ride extends Model { use SoftDeletes; protected $guarded=['id']; protected function casts(): array{return ['active'=>'boolean'];} public function machines(){return $this->hasMany(Machine::class);} }
