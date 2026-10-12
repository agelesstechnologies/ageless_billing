<?php
namespace App\Support;
final class Csv {
 /** Neutralize spreadsheet formulas in free-text export cells. */
 public static function cell(mixed $value):string{
  $s=(string)($value??'');return preg_match('/^[\s\x00-\x1f]*[=+@-]/u',$s)?"'".$s:$s;
 }
 public static function row($handle,array $values):void{fputcsv($handle,array_map(self::cell(...),$values),',','"','');}
}
