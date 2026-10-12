<?php
namespace App\Console\Commands;
use App\Models\{User,Setting};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Validator};
use App\Rules\StrongPassword;
class ParkInstall extends Command {
 protected $signature='park:install {--demo : Add clearly illustrative sample packages and rides}';
 protected $description='Create the first administrator without installing a default password.';
 public function handle():int{
  if(User::where('role','admin')->exists()){$this->warn('An administrator already exists in the configured database. No data or passwords were changed. Run php artisan park:database-check to identify the account, or php artisan park:admin-password to reset its password from this server console.');return self::SUCCESS;}
  $name=$this->ask('Administrator name','Park Administrator');$email=strtolower(trim((string)$this->ask('Administrator email')));
  $password=$this->secret('Choose a password (12+ characters, uppercase, lowercase, number)');$confirm=$this->secret('Confirm password');
  $validator=Validator::make(['name'=>$name,'email'=>$email,'password'=>$password,'password_confirmation'=>$confirm],['name'=>'required|string|max:100','email'=>'required|email|max:255','password'=>['required','confirmed',new StrongPassword]]);
  if($validator->fails()){foreach($validator->errors()->all() as $error)$this->error($error);return self::FAILURE;}
  $email=strtolower(trim((string)$email));
  if(User::whereRaw('LOWER(TRIM(email)) = ?',[$email])->exists()){$this->error('That email belongs to an existing account. Choose a different administrator email. No existing user was changed.');return self::FAILURE;}
  DB::transaction(function()use($name,$email,$password){Setting::firstOrCreate(['id'=>1],['data'=>Setting::defaults()]);User::create(['name'=>$name,'email'=>$email,'password'=>$password,'role'=>'admin','active'=>true]);});
  if($this->option('demo')){$this->call('db:seed',['--class'=>'Database\\Seeders\\DemoSeeder','--force'=>true]);$this->warn('Sample prices and points are illustrative. Review them before real sales. No device tokens or guest passes were seeded.');}
  $this->info('Administrator created. Open /login. Configure park settings, then add an ESP reader.');return self::SUCCESS;
 }
}
