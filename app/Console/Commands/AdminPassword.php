<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Validator};
use App\Rules\StrongPassword;
class AdminPassword extends Command {
 protected $signature='park:admin-password {email? : Administrator login email} {--activate : Explicitly reactivate a disabled administrator}';
 protected $description='Reset an existing administrator password from the trusted server console.';
 public function handle():int{
  $email=$this->argument('email');
  if(!$email){
   $emails=User::where('role','admin')->orderBy('id')->pluck('email')->all();
   if(!$emails){$this->error('No administrator exists in this database. Run php artisan park:install.');return self::FAILURE;}
   $email=$this->choice('Administrator email',$emails,0);
  }
  $matches=User::whereRaw('LOWER(TRIM(email)) = ?',[strtolower(trim((string)$email))])->limit(2)->get();
  if($matches->count()!==1 || $matches->first()->role!=='admin'){$this->error('A unique administrator account was not found. Run php artisan park:database-check.');return self::FAILURE;}
  $user=$matches->first();
  if(!$user->active && !$this->option('activate')){$this->error('This administrator is disabled. Use --activate if you intend to restore this account.');return self::FAILURE;}
  $password=$this->secret('New password');$confirmation=$this->secret('Confirm password');
  $v=Validator::make(['password'=>$password,'password_confirmation'=>$confirmation],['password'=>['required','confirmed',new StrongPassword]]);
  if($v->fails()){$this->error($v->errors()->first());return self::FAILURE;}
  DB::transaction(function()use($user,$password){$data=['password'=>$password];if($this->option('activate'))$data['active']=true;$user->update($data);});
  $this->info('Password updated in the configured project database. Sign in with the selected email and your new password. Existing browser sessions will be rejected on their next authenticated request.');return self::SUCCESS;
 }
}
