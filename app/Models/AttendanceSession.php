<?php
namespace App\Models;
use App\Casts\UtcDateTime;
use App\Domain\AttendancePolicy;
use Illuminate\Database\Eloquent\Model;
class AttendanceSession extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['started_at'=>UtcDateTime::class,'ended_at'=>UtcDateTime::class,'site_snapshot'=>'array','needs_review'=>'boolean'];}
 public function user(){return $this->belongsTo(User::class);}
 public function workSite(){return $this->belongsTo(WorkSite::class);}
 public function punches(){return $this->hasMany(AttendancePunch::class)->orderBy('punched_at');}
 public function corrections(){return $this->hasMany(AttendanceCorrection::class);}
 public function closedSeconds():int{return AttendancePolicy::seconds($this->started_at->getTimestamp(),$this->ended_at?->getTimestamp());}
 public function localTime(string $field,string $format='h:i A'):string{return $this->$field?->setTimezone($this->timezone)->format($format)??'—';}
 public function duration():string{return $this->ended_at?AttendancePolicy::duration($this->closedSeconds()):'On duty';}
 public function inPunch():?AttendancePunch{return $this->punches->firstWhere('type','in');}
 public function outPunch():?AttendancePunch{return $this->punches->firstWhere('type','out');}
 public function workingDuration():string{if($this->ended_at)return AttendancePolicy::duration($this->closedSeconds());return AttendancePolicy::duration(max(0,time()-$this->started_at->getTimestamp()));}
}
