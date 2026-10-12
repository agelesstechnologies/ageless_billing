<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable {
 protected $guarded=['id']; protected $hidden=['password','remember_token'];
 protected function casts():array{return ['password'=>'hashed','active'=>'boolean','attendance_enabled'=>'boolean'];}
 public function workSite(){return $this->belongsTo(WorkSite::class);}
 public function attendanceSessions(){return $this->hasMany(AttendanceSession::class);}
 public function canBill():bool{return in_array($this->role,['admin','cashier'],true);}
 public function jobRole():string{return (string)($this->trade ?: $this->designation ?: 'Staff member');}
}
