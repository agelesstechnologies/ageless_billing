<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Setting extends Model {
 protected $guarded=['id']; protected function casts(): array{return ['data'=>'array'];}
 public static function defaults():array{return ['park_name'=>'Grand Lemurian','tagline'=>'Little tickets. Big adventures.','address'=>'','phone'=>'','tax_id'=>'','logo'=>null,'accent'=>'#7a31b7','secondary'=>'#571e90','currency_symbol'=>'₹','invoice_prefix'=>'AG','tax_label'=>'Tax','tax_rate'=>'0.00','tax_mode'=>'exclusive','receipt_width'=>'82','receipt_footer'=>'Keep this QR safe. Scan at a ride to use your points.','welcome_message'=>'A great day starts here.'];}
 public static function current():array{ $request=app('request');if($request->attributes->has('_park_settings'))return $request->attributes->get('_park_settings');$data=array_replace(self::defaults(),self::find(1)?->data??[]);$request->attributes->set('_park_settings',$data);return $data;}
}
