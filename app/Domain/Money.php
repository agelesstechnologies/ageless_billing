<?php
namespace App\Domain;
use InvalidArgumentException;
final class Money {
 public static function paise(string|int $value):int {
  $s=trim((string)$value);
  if(!preg_match('/^(0|[1-9]\d{0,7})(?:\.(\d{1,2}))?$/D',$s,$m)) throw new InvalidArgumentException('Use a positive amount with at most two decimals.');
  return ((int)$m[1]*100)+(int)str_pad($m[2]??'',2,'0');
 }
 public static function display(int $paise):string{return number_format($paise/100,2,'.',',');}
 public static function total(int $subtotal,int $discount,int $rateBps,string $mode):array {
  if($subtotal<0||$discount<0||$discount>$subtotal||$rateBps<0||$rateBps>10000||!in_array($mode,['inclusive','exclusive'],true)) throw new InvalidArgumentException('Invalid billing totals.');
  $net=$subtotal-$discount;
  $denominator=$mode==='inclusive'?10000+$rateBps:10000;
  // Exact integer half-up rounding; no floating-point accounting.
  $tax=intdiv($net*$rateBps+intdiv($denominator,2),$denominator);
  return ['subtotal_paise'=>$subtotal,'discount_paise'=>$discount,'tax_paise'=>$tax,'total_paise'=>$mode==='inclusive'?$net:$net+$tax];
 }
}
