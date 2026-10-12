<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DatabaseRestore extends Command {
 protected $signature='db:restore {file : Path to a .sql.gz (or .sql) backup} {database : Database to restore into} {--force : Restore into a database that already has tables} {--allow-live : Allow the application database as the target}';
 protected $description='Restore a db:backup file into the named database (created if missing). Refuses the application database unless --allow-live.';

 public function handle():int {
  $conn=config('database.connections.'.config('database.default'));
  $file=$this->argument('file');$db=$this->argument('database');
  if(($conn['driver']??null)!=='mysql'){$this->error('db:restore supports the mysql connection only.');return self::FAILURE;}
  if(!preg_match('/^[A-Za-z0-9_]{1,64}$/',$db)){$this->error('Database name may contain only letters, digits and underscore.');return self::FAILURE;}
  if(strcasecmp($db,$conn['database'])===0&&!$this->option('allow-live')){
   $this->error("'$db' is the application database. Restore into a scratch database, or pass --allow-live if you really mean it.");return self::FAILURE;
  }
  if(!is_file($file)){$this->error("File not found: $file");return self::FAILURE;}
  $mysql=config('backup.mysql');
  if(!is_file($mysql)){$this->error("mysql client not found at $mysql. Set BACKUP_MYSQL in .env.");return self::FAILURE;}
  $gz=str_ends_with(strtolower($file),'.gz');
  $in=$gz?gzopen($file,'rb'):fopen($file,'rb');
  if(!$in){$this->error('Cannot open backup file.');return self::FAILURE;}
  $env=['MYSQL_PWD'=>(string)$conn['password']];
  $base=[$mysql,'--host='.$conn['host'],'--port='.$conn['port'],'--user='.$conn['username'],'--default-character-set=utf8mb4'];

  $count=new Process([...$base,'--batch','--skip-column-names','-e',"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db'"],null,$env);
  $count->run();
  if(!$count->isSuccessful()){$this->error('Cannot query MySQL: '.trim($count->getErrorOutput()));return self::FAILURE;}
  if((int)trim($count->getOutput())>0&&!$this->option('force')){
   $this->error("'$db' already contains tables. Use a new name or pass --force to load over it.");return self::FAILURE;
  }
  $create=new Process([...$base,'-e',"CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"],null,$env);
  $create->run();
  if(!$create->isSuccessful()){$this->error('Cannot create database: '.trim($create->getErrorOutput()));return self::FAILURE;}

  $feed=function()use($in,$gz){while(!($gz?gzeof($in):feof($in))){$chunk=$gz?gzread($in,1<<20):fread($in,1<<20);if($chunk===false||$chunk==='')break;yield $chunk;}};
  $load=new Process([...$base,'--database='.$db],null,$env,$feed());
  $load->setTimeout(null)->run();
  $gz?gzclose($in):fclose($in);
  if(!$load->isSuccessful()){$this->error('Restore failed: '.trim($load->getErrorOutput()));return self::FAILURE;}
  $this->info('Restored '.basename($file)." into database '$db'.");
  return self::SUCCESS;
 }
}
