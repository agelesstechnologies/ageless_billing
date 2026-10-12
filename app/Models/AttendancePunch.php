<?php
namespace App\Models;
use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
class AttendancePunch extends Model {
 public $timestamps=false; protected $guarded=['id'];
 protected function casts():array{return ['punched_at'=>UtcDateTime::class,'latitude'=>'float','longitude'=>'float','accuracy_m'=>'float','distance_m'=>'integer'];}
 public function session(){return $this->belongsTo(AttendanceSession::class,'attendance_session_id');}
 public function user(){return $this->belongsTo(User::class);}
 public function mapUrl():string{return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->latitude.','.$this->longitude);}
}
