<?php
namespace App\Services;
use App\Models\AuditLog;
class Audit { public static function record(string $action,string $type,?int $id=null,array $details=[]):void{AuditLog::create(['user_id'=>auth()->id(),'action'=>$action,'entity_type'=>$type,'entity_id'=>$id,'details'=>$details]);} }
