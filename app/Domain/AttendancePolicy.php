<?php
namespace App\Domain;
/** Framework-independent rules. Browser coordinates are self-reported, not identity proof. */
final class AttendancePolicy {
 public static function distance(float $lat1,float $lon1,float $lat2,float $lon2): float {
  foreach ([$lat1,$lon1,$lat2,$lon2] as $n) if (!is_finite($n)) throw new \InvalidArgumentException('Coordinates must be finite.');
  if (abs($lat1)>90 || abs($lat2)>90 || abs($lon1)>180 || abs($lon2)>180) throw new \InvalidArgumentException('Coordinates out of range.');
  $a=sin(deg2rad($lat2-$lat1)/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin(deg2rad($lon2-$lon1)/2)**2;
  return 6371008.8 * 2 * atan2(sqrt(max(0,min(1,$a))),sqrt(max(0,1-$a)));
 }
 public static function location(array $site,float $lat,float $lon,float $accuracy,string $action): array {
  if (!in_array($action,['in','out'],true)) throw new \InvalidArgumentException('Invalid punch action.');
  self::distance($lat,$lon,$lat,$lon);
  if (!is_finite($accuracy) || $accuracy<0 || $accuracy>50000) throw new \InvalidArgumentException('Invalid location accuracy.');
  $configured=isset($site['latitude'],$site['longitude']);
  $distance=$configured ? self::distance((float)$site['latitude'],(float)$site['longitude'],$lat,$lon) : null;
  $enforce=(bool)($site['enforce_geofence']??false);
  $status='recorded'; $message='Location recorded. Site-radius enforcement is off.';
  if ($accuracy>(int)($site['max_accuracy_m']??100)) {$status='poor_accuracy';$message='Location is not accurate enough. Move to an open area and try again.';}
  elseif ($enforce && !$configured) {$status='not_configured';$message='Your administrator must configure the site location first.';}
  elseif ($enforce && $distance>(int)$site['radius_m']) {$status='outside';$message='You are outside the permitted site radius.';}
  elseif ($enforce) {$status='inside';$message='Location is within the configured site radius.';}
  $review=in_array($status,['poor_accuracy','not_configured','outside'],true);
  // Never trap a member in an open shift because they have already left the site.
  return ['allowed'=>$action==='out'||!$review,'needs_review'=>$review,'status'=>$status,'message'=>$message,'distance_m'=>$distance===null?null:(int)round($distance)];
 }
 public static function seconds(int $start,?int $end): int { return $end===null ? 0 : max(0,$end-$start); }
 public static function overlaps(int $aStart,?int $aEnd,int $bStart,?int $bEnd): bool {
  return $aStart<($bEnd??PHP_INT_MAX) && $bStart<($aEnd??PHP_INT_MAX);
 }
 public static function duration(int $seconds): string { $m=intdiv(max(0,$seconds),60); return sprintf('%dh %02dm',intdiv($m,60),$m%60); }
}
