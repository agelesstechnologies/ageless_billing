<?php
namespace App\Casts;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
/** Store UTC explicitly. Do not use a local-time naive cast for attendance timestamps. */
class UtcDateTime implements CastsAttributes {
 public function get(Model $model,string $key,mixed $value,array $attributes): ?CarbonImmutable {
  return $value===null ? null : CarbonImmutable::parse($value,'UTC');
 }
 public function set(Model $model,string $key,mixed $value,array $attributes): ?string {
  if ($value===null) return null;
  $date=$value instanceof \DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value,'UTC');
  return $date->utc()->format('Y-m-d H:i:s');
 }
}
