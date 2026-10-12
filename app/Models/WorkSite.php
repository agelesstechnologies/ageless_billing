<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WorkSite extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['active'=>'boolean','enforce_geofence'=>'boolean','latitude'=>'float','longitude'=>'float','radius_m'=>'integer','max_accuracy_m'=>'integer'];}
 public function users(){return $this->hasMany(User::class);}
 public function snapshot():array{return $this->only(['name','timezone','latitude','longitude','radius_m','max_accuracy_m','enforce_geofence']);}
}
