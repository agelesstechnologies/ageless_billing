<?php
namespace Tests\Feature;

use App\Models\{User,Setting,WorkSite,AttendanceSession};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseLoginAndMobileAdminTest extends TestCase {
 use RefreshDatabase;
 private function account(string $email='Admin@Example.test',string $role='admin',bool $active=true):User {
  return User::create(['name'=>'Database Account','email'=>$email,'password'=>'OriginalPassword123!','role'=>$role,'active'=>$active]);
 }
 private function login(string $email,string $password='OriginalPassword123!') {
  return $this->post('/login',['email'=>$email,'password'=>$password]);
 }
 public function test_login_finds_legacy_case_and_whitespace_email_in_the_database():void {
  $user=$this->account('  ADMIN@Example.test  ');
  $this->login(' admin@example.test ')->assertRedirect('/');
  $this->assertAuthenticatedAs($user);
 }
 public function test_wrong_password_cannot_authenticate_an_existing_database_account():void {
  $this->account();$this->login('admin@example.test','WrongPassword123!')->assertSessionHasErrors('email');$this->assertGuest();
 }
 public function test_disabled_database_account_cannot_log_in():void {
  $this->account('admin@example.test','admin',false);$this->login('admin@example.test')->assertSessionHasErrors('email');$this->assertGuest();
 }
 public function test_ambiguous_legacy_emails_fail_instead_of_picking_an_account():void {
  $this->account('ADMIN@example.test');$this->account('admin@example.test','staff');
  $this->login('admin@example.test')->assertSessionHasErrors('email');$this->assertGuest();
 }
 public function test_admin_login_returns_to_mobile_management_using_database_role():void {
  Setting::create(['data'=>Setting::defaults()]);$user=$this->account();
  $this->get('/mobile')->assertRedirect('/login');
  $this->login('admin@example.test')->assertRedirect('/mobile');
  $this->get('/mobile')->assertOk()->assertSee('Add employee')->assertSee('Attendance register')->assertSee('ageless-user-role',false);
  $this->assertAuthenticatedAs($user);
 }
 public function test_staff_mobile_home_redirects_to_attendance_and_denies_admin_actions():void {
  $user=$this->account('staff@example.test','staff');$this->login($user->email)->assertRedirect('/staff');
  $this->get('/mobile')->assertRedirect('/staff');
  $this->get('/users/create')->assertForbidden();$this->post('/users',[])->assertForbidden();
 }
 public function test_admin_creates_database_user_who_can_log_in_and_punch_into_same_database():void {
  Setting::create(['data'=>Setting::defaults()]);
  $site=WorkSite::create(['name'=>'Database Site','timezone'=>'Asia/Kolkata','latitude'=>10,'longitude'=>76.3,'radius_m'=>200,'max_accuracy_m'=>100,'active'=>true,'enforce_geofence'=>true]);
  $this->account();$this->login('admin@example.test');
  $this->post('/users',['name'=>'New Database Staff','email'=>' NEW@Example.test ','password'=>'StaffPassword123!','password_confirmation'=>'StaffPassword123!','role'=>'staff','active'=>1,'attendance_enabled'=>1,'work_site_id'=>$site->id])->assertRedirect('/users');
  $staff=User::where('email','new@example.test')->firstOrFail();
  $this->assertTrue(Hash::check('StaffPassword123!',$staff->password));$this->assertNotSame('StaffPassword123!',$staff->password);
  $this->assertDatabaseHas('audit_logs',['action'=>'user.created','entity_id'=>$staff->id]);
  $this->post('/logout');$this->login('NEW@example.test','StaffPassword123!')->assertRedirect('/staff');
  $this->postJson('/staff/punch',['action'=>'in','request_id'=>(string)Str::uuid(),'latitude'=>10.0001,'longitude'=>76.3001,'accuracy_m'=>12])->assertOk()->assertJsonPath('ok',true);
  $session=AttendanceSession::where('user_id',$staff->id)->firstOrFail();
  $this->postJson('/staff/punch',['action'=>'out','session_id'=>$session->id,'request_id'=>(string)Str::uuid(),'latitude'=>10.0001,'longitude'=>76.3001,'accuracy_m'=>12])->assertOk();
  $this->assertDatabaseCount('attendance_punches',2);$this->assertDatabaseHas('attendance_sessions',['id'=>$session->id,'user_id'=>$staff->id,'open_user_id'=>null]);
 }
 public function test_admin_cannot_create_normalized_duplicate_of_legacy_email():void {
  $this->account();$this->account(' LEGACY@Example.test ','staff');$this->login('admin@example.test');
  $this->post('/users',['name'=>'Duplicate','email'=>'legacy@example.test','password'=>'Password123!','password_confirmation'=>'Password123!','role'=>'staff'])->assertSessionHasErrors('email');
  $this->assertDatabaseCount('users',2);
 }
 public function test_console_installer_adds_first_admin_to_staff_only_database_without_reset():void {
  $staff=$this->account('staff@example.test','staff');$hash=$staff->password;
  $this->artisan('park:install')->expectsQuestion('Administrator name','New Administrator')->expectsQuestion('Administrator email',' NEWADMIN@Example.test ')->expectsQuestion('Choose a password (12+ characters, uppercase, lowercase, number)','NewAdminPassword123!')->expectsQuestion('Confirm password','NewAdminPassword123!')->assertSuccessful();
  $this->assertDatabaseHas('users',['email'=>'newadmin@example.test','role'=>'admin']);$this->assertSame($hash,$staff->fresh()->password);$this->assertDatabaseCount('users',2);
 }
 public function test_installer_preserves_existing_admin_account_and_password():void {
  $admin=$this->account();$hash=$admin->password;
  $this->artisan('park:install')->expectsOutputToContain('No data or passwords were changed')->assertSuccessful();
  $this->assertSame($hash,$admin->fresh()->password);$this->assertDatabaseCount('users',1);
 }
 public function test_database_diagnostic_identifies_login_account_and_does_not_mutate_data():void {
  $admin=$this->account();$hash=$admin->password;
  $this->artisan('park:database-check')->expectsOutput('Database connection: OK')->expectsOutputToContain('not DB_USERNAME / DB_PASSWORD')->expectsOutputToContain('No accounts or passwords were changed')->assertSuccessful();
  $this->assertSame($hash,$admin->fresh()->password);$this->assertDatabaseCount('users',1);$this->assertDatabaseCount('audit_logs',0);
 }
 public function test_database_diagnostic_reports_ambiguous_emails():void {
  $this->account('Admin@example.test');$this->account('admin@example.test','staff');
  $this->artisan('park:database-check')->expectsOutputToContain('Ambiguous login emails exist')->assertFailed();
 }
 public function test_owner_console_resets_existing_admin_password_and_old_session_is_revoked():void {
  $admin=$this->account();$this->login('admin@example.test');
  $this->artisan('park:admin-password',['email'=>'admin@example.test'])->expectsQuestion('New password','RecoveredPassword123!')->expectsQuestion('Confirm password','RecoveredPassword123!')->assertSuccessful();
  $this->assertTrue(Hash::check('RecoveredPassword123!',$admin->fresh()->password));
  // A real next HTTP request loads the user again from the session provider.
  // Laravel feature tests share the guard instance between requests.
  \Illuminate\Support\Facades\Auth::forgetGuards();
  $this->get('/mobile')->assertRedirect('/login');
  $this->login('admin@example.test','RecoveredPassword123!')->assertRedirect('/');
  $this->assertAuthenticated();
 }
 public function test_password_recovery_without_email_offers_actual_database_admins():void {
  $admin=$this->account();
  $this->artisan('park:admin-password')->expectsChoice('Administrator email',$admin->email,[$admin->email])->expectsQuestion('New password','RecoveredPassword123!')->expectsQuestion('Confirm password','RecoveredPassword123!')->assertSuccessful();
  $this->assertTrue(Hash::check('RecoveredPassword123!',$admin->fresh()->password));
 }
 public function test_disabled_admin_requires_explicit_activation_from_server_console():void {
  $admin=$this->account('admin@example.test','admin',false);$hash=$admin->password;
  $this->artisan('park:admin-password',['email'=>$admin->email])->expectsOutputToContain('Use --activate')->assertFailed();
  $this->assertSame($hash,$admin->fresh()->password);
  $this->artisan('park:admin-password',['email'=>$admin->email,'--activate'=>true])->expectsQuestion('New password','RecoveredPassword123!')->expectsQuestion('Confirm password','RecoveredPassword123!')->assertSuccessful();
  $this->assertTrue($admin->fresh()->active);$this->login('admin@example.test','RecoveredPassword123!')->assertRedirect('/');
 }
}
