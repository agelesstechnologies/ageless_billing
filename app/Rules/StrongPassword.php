<?php
namespace App\Rules;

use Illuminate\Validation\Rules\Password;

/** The one password policy for every place that sets a password: 12+ characters, mixed case, a number. */
class StrongPassword extends Password {
 public const MIN_LENGTH=12;
 /** Random password that always satisfies this policy (for seeders and generated accounts). */
 public static function generate():string{
  $pick=fn(string $set)=>$set[random_int(0,strlen($set)-1)];
  $chars=[$pick('ABCDEFGHJKLMNPQRSTUVWXYZ'),$pick('abcdefghijkmnopqrstuvwxyz'),$pick('23456789')];
  $all='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  while(count($chars)<16)$chars[]=$pick($all);
  shuffle($chars);return implode('',$chars);
 }
 public function __construct(){parent::__construct(self::MIN_LENGTH);$this->mixedCase()->numbers();}
}
