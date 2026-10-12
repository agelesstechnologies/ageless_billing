<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Schema};

class DatabaseCheck extends Command {
 protected $signature='park:database-check';
 protected $description='Check the configured project database and administrator accounts without changing data.';
 public function handle():int {
  try {
   $connection=DB::connection();
   $connection->getPdo();
   $this->info('Database connection: OK');
   $this->line('Driver: '.$connection->getDriverName());
   $this->line('Database: '.$connection->getDatabaseName());
   $missing=[];
   foreach(['users','settings','work_sites','attendance_punches','attendance_sessions','attendance_corrections','audit_logs'] as $table) {
    if(!Schema::hasTable($table)) $missing[]=$table;
   }
   if($missing) {
    $this->error('Missing tables: '.implode(', ',$missing));
    $this->line('Run php artisan migrate --force using this project configuration.');
    return self::FAILURE;
   }
   $this->line('Users: '.User::count().' | Attendance punches: '.DB::table('attendance_punches')->count());
   $admins=User::where('role','admin')->orderBy('id')->get(['name','email','active']);
   if($admins->isEmpty()) {
    $this->warn('No administrator exists. Run php artisan park:install to create the first administrator.');
   } else {
    $this->table(['Administrator','Login email','Status'], $admins->map(fn($u)=>[$u->name,trim($u->email),$u->active?'Active':'Disabled'])->all());
    $this->line('Sign in with an account email above and its application password, not DB_USERNAME / DB_PASSWORD.');
    $this->line('Forgotten password: php artisan park:admin-password');
    if(!$admins->contains('active',true)) $this->warn('No active administrator. To restore an account explicitly: php artisan park:admin-password --activate');
   }
   $duplicates=DB::table('users')->selectRaw('LOWER(TRIM(email)) AS normalized_email')->groupByRaw('LOWER(TRIM(email))')->havingRaw('COUNT(*) > 1')->get();
   if($duplicates->isNotEmpty()) {
    $this->error('Ambiguous login emails exist. Give these accounts distinct emails from the trusted server console:');
    foreach($duplicates as $duplicate) $this->line($duplicate->normalized_email);
    return self::FAILURE;
   }
   $this->info('Users and attendance use this same configured database. No accounts or passwords were changed.');
   return self::SUCCESS;
  } catch(\Throwable $error) {
   $this->error('Cannot check the configured project database. Check .env DB_CONNECTION, DB_DATABASE, DB_HOST, DB_PORT and the server credentials; check PHP PDO extensions and database permissions.');
   $this->line('After changing .env, run php artisan config:clear and repeat this check.');
   return self::FAILURE;
  }
 }
}
