<?php
namespace App\Services;

use Illuminate\Database\Connection;
use PDO;
use RuntimeException;

/** One-time copy into a new migrated MySQL database. No models/password casts. */
class SqliteToMysqlImporter {
 public const TABLES=['work_sites','users','settings','packages','rides','machines','park_passes','sales','sale_items','redemptions','point_entries','audit_logs','attendance_sessions','attendance_punches','attendance_corrections'];

 public function transfer(Connection $target,string $path,bool $execute=false):array {
  if($target->getDriverName()!=='mysql')throw new RuntimeException('Select DB_CONNECTION=mysql before importing. The target must be MySQL.');
  $real=realpath($path);
  if(!$real || !is_file($real) || !is_readable($real))throw new RuntimeException('SQLite source file not found or unreadable. No source file was created.');
  if(!extension_loaded('pdo_sqlite'))throw new RuntimeException('Enable pdo_sqlite temporarily to read the old database; pdo_mysql remains the application driver.');
  $source=new PDO('sqlite:'.$real,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::SQLITE_ATTR_OPEN_FLAGS=>PDO::SQLITE_OPEN_READONLY]);
  $source->exec('PRAGMA query_only=ON');$source->beginTransaction();
  try {
   if($source->query('PRAGMA quick_check')->fetchColumn()!=='ok')throw new RuntimeException('SQLite integrity check failed. Restore a verified backup before importing.');
   $tables=$source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
   if(array_diff(self::TABLES,$tables) || array_diff($tables,[...self::TABLES,'migrations']))throw new RuntimeException('Source tables differ from this ERP release. Update/check the SQLite source first; custom tables are not silently omitted.');
   $schema=$target->getSchemaBuilder();$counts=[];
   foreach(self::TABLES as $table) {
    if(!$schema->hasTable($table))throw new RuntimeException('Target tables are missing. Run php artisan migrate --force first.');
    $columns=array_column($source->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC),'name');
    if(array_diff($columns,$schema->getColumnListing($table)))throw new RuntimeException('Source/target columns differ. Apply the latest ERP migrations before importing.');
    $counts[$table]=(int)$source->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn();
   }
   $engines=$target->select('SELECT TABLE_NAME AS table_name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
   foreach($engines as $table)if(in_array($table->table_name,self::TABLES,true) && strtoupper((string)$table->engine)!=='INNODB')throw new RuntimeException('Import requires InnoDB tables for rollback. No data was copied.');
   $this->emptyTarget($target);
   if($execute) {
    // No DDL, TRUNCATE or disabled foreign keys inside this transaction.
    // IDs, wallet balances, token/password hashes and history are copied raw.
    $target->transaction(function()use($target,$source,$counts){
     $this->emptyTarget($target);
     $target->table('work_sites')->delete(); // Only the checked migration placeholder.
     foreach(self::TABLES as $table) {
      $query=$source->query('SELECT * FROM "'.$table.'" ORDER BY id');$batch=[];
      while($row=$query->fetch(PDO::FETCH_ASSOC)) {
       $batch[]=$row;
       if(count($batch)>=200){$target->table($table)->insert($batch);$batch=[];}
      }
      if($batch)$target->table($table)->insert($batch);
      if($target->table($table)->count()!==$counts[$table])throw new RuntimeException('Imported row counts did not match. Import rolled back.');
     }
    });
   }
   return $counts;
  }finally {if($source->inTransaction())$source->rollBack();}
 }
 private function emptyTarget(Connection $target):void {
  foreach(self::TABLES as $table) {
   if($table==='work_sites')continue;
   if($target->table($table)->exists())throw new RuntimeException('Target already contains ERP data. Import refuses to merge, overwrite or duplicate existing records. Use a new empty MySQL database.');
  }
  $sites=$target->table('work_sites')->get();
  if($sites->isEmpty())return;
  $site=$sites->first();
  if($sites->count()!==1 || (int)$site->id!==1 || $site->name!=='Main park' || $site->address!==null || $site->latitude!==null || $site->longitude!==null || $site->timezone!=='Asia/Kolkata' || (int)$site->radius_m!==200 || (int)$site->max_accuracy_m!==100 || (bool)$site->enforce_geofence || !(bool)$site->active)throw new RuntimeException('Target has configured work sites. Import requires an empty database or the untouched migration placeholder.');
 }
}
