<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DatabaseBackup extends Command {
 protected $signature='db:backup {--database= : Database to dump (default: the application database)} {--no-copy : Skip the second-location copy}';
 protected $description='Dump MySQL to a timestamped .sql.gz in the backup folder, copy it to the second location and prune old backups.';

 public function handle():int {
  $conn=config('database.connections.'.config('database.default'));
  if(($conn['driver']??null)!=='mysql'){$this->error('db:backup supports the mysql connection only (current: '.($conn['driver']??'unknown').').');return self::FAILURE;}
  $db=$this->option('database')?:$conn['database'];
  if(!preg_match('/^[A-Za-z0-9_$]{1,64}$/',$db)){$this->error('Invalid database name.');return self::FAILURE;}
  $exe=config('backup.mysqldump');
  if(!is_file($exe)){$this->error("mysqldump not found at $exe. Set BACKUP_MYSQLDUMP in .env.");return self::FAILURE;}
  $dir=config('backup.path');
  if(!File::isDirectory($dir)&&!File::makeDirectory($dir,0750,true,true)){$this->error("Cannot create $dir.");return self::FAILURE;}

  $final=$dir.DIRECTORY_SEPARATOR.$db.'_'.now()->format('Ymd_His').'.sql.gz';
  $part=$final.'.part';
  $gz=gzopen($part,'wb6');
  if(!$gz){$this->error("Cannot write $part.");return self::FAILURE;}
  $tail='';$err='';
  $args=[$exe,'--host='.$conn['host'],'--port='.$conn['port'],'--user='.$conn['username'],'--single-transaction','--quick','--routines','--triggers','--events','--no-tablespaces','--default-character-set=utf8mb4',$db];
  // Password goes through the environment, never the command line.
  $p=new Process($args,null,['MYSQL_PWD'=>(string)$conn['password']]);
  $p->setTimeout(null)->run(function($type,$buf)use($gz,&$tail,&$err){
   if($type===Process::OUT){gzwrite($gz,$buf);$tail=substr($tail.$buf,-200);}else $err.=$buf;
  });
  gzclose($gz);
  // mysqldump ends a complete dump with "-- Dump completed"; anything else is truncated.
  if(!$p->isSuccessful()||!str_contains($tail,'-- Dump completed')){
   @unlink($part);$this->error('Backup failed: '.trim($err?:'dump incomplete'));return self::FAILURE;
  }
  rename($part,$final);
  $this->info('Backup written: '.$final.' ('.number_format(filesize($final)).' bytes)');

  $status=self::SUCCESS;
  if(!$this->option('no-copy')&&config('backup.copy_to')!==''&&!$this->copy($final))$status=self::FAILURE;
  $this->prune($dir);
  return $status;
 }

 private function copy(string $file):bool {
  $dest=config('backup.copy_to');
  // "name:path" with a 2+ character name is an rclone remote; "D:\x" is a drive path.
  if(preg_match('/^[A-Za-z0-9_.-]{2,}:/',$dest)){
   $p=new Process([config('backup.rclone'),'copy',$file,$dest,'--immutable']);
   $p->setTimeout(3600)->run();
   if(!$p->isSuccessful()){$this->error('rclone copy failed: '.trim($p->getErrorOutput()?:$p->getOutput()));return false;}
  }else{
   if(!is_dir($dest)&&!@mkdir($dest,0777,true)){$this->error("Second location not reachable: $dest");return false;}
   $target=rtrim($dest,'\\/').DIRECTORY_SEPARATOR.basename($file);
   if(!@copy($file,$target)||filesize($target)!==filesize($file)){@unlink($target);$this->error("Copy to $target failed.");return false;}
  }
  $this->info('Copied to second location: '.$dest);
  return true;
 }

 private function prune(string $dir):void {
  $cutoff=now()->subDays(max(1,config('backup.retention_days')))->getTimestamp();
  foreach(glob($dir.DIRECTORY_SEPARATOR.'*.sql.gz')?:[] as $f){
   if(filemtime($f)<$cutoff&&@unlink($f))$this->line('Deleted old backup: '.basename($f));
  }
 }
}
