<?php
namespace App\Services;

use App\Models\Machine;
use Illuminate\Validation\ValidationException;

class FirmwareBundle {
 public static function suggestedUrl(string $value):string {
  try{return self::serverUrl($value);}catch(ValidationException $e){return '';}
 }
 public static function serverUrl(string $value):string {
  $value=rtrim(trim($value),'/');$parts=parse_url($value);
  if(!$parts || !in_array($parts['scheme']??'',['http','https'],true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || preg_match('/[\x00-\x20\x7f]/',$value)) {
   throw ValidationException::withMessages(['server_url'=>'Enter the ERP base URL without a password, query or fragment.']);
  }
  $host=strtolower($parts['host']);
  if(in_array($host,['localhost','127.0.0.1','[::1]'],true)) throw ValidationException::withMessages(['server_url'=>'Use the server PC LAN IP or hosted address. localhost refers to the ESP32 itself.']);
  if($parts['scheme']==='http') {
   $ip=filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4);
   $local=$ip && (str_starts_with($host,'10.') || str_starts_with($host,'192.168.') || preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./',$host));
   if(!$local && !str_ends_with($host,'.local')) throw ValidationException::withMessages(['server_url'=>'Use HTTPS for a hosted ERP, or a private LAN IP for local HTTP.']);
  }
  return $value;
 }
 private static function literal(string $s):string {
  return '"'.str_replace(["\\","\"","\r","\n"],["\\\\","\\\"","\\r","\\n"],$s).'"';
 }
 public static function config(Machine $machine,string $token,string $url):string {
  $source=file_get_contents(base_path('firmware/AgelessCameraReader/Config.h'));
  if($source===false) throw new \RuntimeException('Camera firmware template missing.');
  return str_replace(['"YOUR_ERP_SERVER_URL"','"PASTE_UNIQUE_READER_TOKEN"','"YOUR_READER_SERIAL"','EXPECTED_MACHINE_ID = 0'],[self::literal($url),self::literal($token),self::literal($machine->serial),'EXPECTED_MACHINE_ID = '.$machine->id],$source);
 }
 /** Standard ZIP (deflate when available); no ZipArchive extension required. */
 public static function zip(array $files):string {
  $body='';$directory='';$count=0;
  foreach($files as $name=>$content) {
   if(str_starts_with($name,'/') || str_contains($name,'..') || str_contains($name,'\\'))throw new \InvalidArgumentException('Unsafe archive path.');
   $content=(string)$content;$method=function_exists('gzdeflate')?8:0;$packed=$method===8?gzdeflate($content,6):$content;
   if($packed===false)throw new \RuntimeException('Cannot compress firmware.');
   $crc=crc32($content);$offset=strlen($body);$flags=0x800;$date=((2026-1980)<<9)|(10<<5)|9;
   $body.=pack('VvvvvvVVVvv',0x04034b50,20,$flags,$method,0,$date,$crc,strlen($packed),strlen($content),strlen($name),0).$name.$packed;
   $directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,$flags,$method,0,$date,$crc,strlen($packed),strlen($content),strlen($name),0,0,0,0,0,$offset).$name;
   $count++;
  }
  return $body.$directory.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($directory),strlen($body),0);
 }
 public static function pack(array $devices,string $url,array $retained=[]):string {
  $base=base_path('firmware/AgelessCameraReader');$prefix='AgelessESP32Camera/';$files=[];
  foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base.'/lib/ESP32QRCodeReader',\FilesystemIterator::SKIP_DOTS)) as $file) {
   if($file->isFile())$files[$prefix.'libraries/ESP32QRCodeReader/'.str_replace('\\','/',substr($file->getPathname(),strlen($base.'/lib/ESP32QRCodeReader/') ))]=file_get_contents($file->getPathname());
  }
  $mapping=[['Ride','Reader serial','Machine ID','Points per play','Reader status']];
  foreach($devices as $i=>$device) {
   $machine=$device['machine'];$folder='Ride_'.sprintf('%02d',$i+1).'_ESP_CAM';$dir=$prefix.$folder.'/';
   $files[$dir.$folder.'.ino']=file_get_contents($base.'/AgelessCameraReader.ino');
   foreach(['ReaderCore.h','ScanGate.h','platformio.ini'] as $name)$files[$dir.$name]=file_get_contents($base.'/'.$name);
   $files[$dir.'Config.h']=self::config($machine,$device['token'],$url);
   $mapping[]=[$machine->ride->name,$machine->serial,$machine->id,$machine->ride->points_per_play,'Disabled: enable after bench test'];
  }
  $stream=fopen('php://temp','r+');foreach($mapping as $row){
   $row=array_map(fn($value)=>is_string($value) && preg_match('/^[=+@\-\t\r]/',$value)?"'".$value:$value,$row);
   fputcsv($stream,$row,',','"','');
  }rewind($stream);$files[$prefix.'Reader_Mapping.csv']=stream_get_contents($stream);fclose($stream);
  $files[$prefix.'START-HERE-MALAYALAM.md']=file_get_contents(base_path('docs/ESP32-CAMERA-MALAYALAM.md'));
  $files[$prefix.'README.txt']="Private one-time firmware pack. Config.h contains device tokens; keep this ZIP with the park administrator. The database stores hashes only.\nTarget: AI-Thinker ESP32-CAM, OV2640, PSRAM; no microSD.\nInstall the supplied camera library once. Edit Wi-Fi in each Config.h. Default BENCH mode makes no debit or pulse. The server controls ride points.\nNew readers are disabled until tested. Enable them in ESP readers, and set ENABLE_PULSE_OUTPUT=true only for the isolated credit input.\nExisting readers retained without token rotation: ".implode(', ',$retained)."\nAn existing reader uses its original Config.h. To recover a lost token, first resolve pending plays, then explicitly Rotate device token in ERP and download Config.h.\n";
  return self::zip($files);
 }
}
