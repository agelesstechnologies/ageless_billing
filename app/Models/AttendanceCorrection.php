<?php
namespace App\Models;
use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
class AttendanceCorrection extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['before_values'=>'array','after_values'=>'array','reviewed_at'=>UtcDateTime::class];}
 public function user(){return $this->belongsTo(User::class);}
 public function session(){return $this->belongsTo(AttendanceSession::class,'attendance_session_id');}
 public function workSite(){return $this->belongsTo(WorkSite::class);}
 public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');}
}
