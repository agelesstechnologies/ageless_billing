<?php
namespace App\Services;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Common\ErrorCorrectionLevel;
class Qr {
 public static function svg(string $text):string {
  $matrix=Encoder::encode($text,ErrorCorrectionLevel::M(),'ISO-8859-1')->getMatrix();
  $n=$matrix->getWidth();$size=$n+8;$path='';
  for($y=0;$y<$n;$y++) for($x=0;$x<$n;$x++) if($matrix->get($x,$y)===1) $path.='M'.($x+4).' '.($y+4).'h1v1h-1z';
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" role="img" aria-label="Ride points QR pass" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="white"/><path d="'.$path.'" fill="black"/></svg>';
 }
}
