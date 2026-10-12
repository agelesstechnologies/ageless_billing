<?php
namespace App\Console\Commands;

use App\Services\SqliteToMysqlImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportSqlite extends Command {
 protected $signature='park:import-sqlite {path : Existing SQLite source file} {--execute : Copy into the empty migrated MySQL database}';
 protected $description='Preview or import existing SQLite ERP data into a new MySQL database without resetting passwords or balances.';
 public function handle(SqliteToMysqlImporter $importer):int {
  try {
   if($this->option('execute') && !app()->isDownForMaintenance())throw new RuntimeException('Run php artisan down before --execute. Stop the old SQLite installation/readers too, so no billing or punches occur during the transfer.');
   $counts=$importer->transfer(DB::connection(),(string)$this->argument('path'),(bool)$this->option('execute'));
   $this->table(['Table','Source records'],array_map(fn($name,$count)=>[$name,$count],array_keys($counts),array_values($counts)));
   if(!$this->option('execute')) {
    $this->info('Preview only. No MySQL records were changed.');
    $this->line('After backup and stopping writes: php artisan down, then repeat this command with --execute.');
   }else {
    $this->info('Import complete. IDs, password hashes, reader token hashes, bills, QR balances and attendance retained.');
    $this->line('The source SQLite file was opened read-only. Run park:database-check, then php artisan up.');
   }
   return self::SUCCESS;
  }catch(RuntimeException $error) {
   // Database exceptions may include row values. Show our validation messages
   // without exposing a query and its password/token/QR bindings.
   if(get_class($error)===RuntimeException::class)$this->error($error->getMessage());
   else $this->error('Import failed. Any row changes were rolled back. Check source data, schema and MySQL permissions; no password reset was performed.');
   return self::FAILURE;
  }catch(\Throwable $error) {
   $this->error('Import failed. Any row changes were rolled back. Check source data, schema and MySQL permissions; no password reset was performed.');
   return self::FAILURE;
  }
 }
}
