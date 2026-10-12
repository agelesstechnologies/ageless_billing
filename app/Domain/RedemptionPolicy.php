<?php
namespace App\Domain;
final class RedemptionPolicy {
 public const APPROVAL_SECONDS=30;
 public static function deny(bool $enabled,bool $rideActive,bool $exists,string $status,int $expiry,int $now,int $balance,int $cost,bool $pending,bool $cooldown):?string {
  if(!$enabled) return 'MACHINE_DISABLED';
  if(!$rideActive) return 'RIDE_DISABLED';
  if($pending) return 'MACHINE_PENDING';
  if(!$exists) return 'INVALID_QR';
  if($status!=='active') return 'PASS_BLOCKED';
  if($expiry<=$now) return 'PASS_EXPIRED';
  if($cooldown) return 'COOLDOWN';
  if($balance<$cost) return 'INSUFFICIENT_POINTS';
  return null;
 }
 public static function mayPulse(string $status,int $authorizedAt,int $now):bool{return $status==='approved' && $now-$authorizedAt<self::APPROVAL_SECONDS && $now>=$authorizedAt;}
}
