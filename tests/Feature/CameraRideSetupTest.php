<?php
namespace Tests\Feature;

use App\Models\{User,Setting,Ride,Machine,Redemption,ParkPass};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CameraRideSetupTest extends TestCase {
 use RefreshDatabase;
 private User $admin;
 protected function setUp():void {
  parent::setUp();Setting::create(['data'=>Setting::defaults()]);
  $this->admin=User::create(['name'=>'Camera Setup Admin','email'=>'admin@example.test','password'=>'TestPassword123!','role'=>'admin','active'=>true]);
 }
 private function rows():array {
  return array_map(fn($n)=>['name'=>'Test ride '.$n,'code'=>'RIDE-'.sprintf('%02d',$n),'points_per_play'=>$n,'pulse_ms'=>250,'cooldown_seconds'=>5,'active'=>true],range(1,25));
 }
 private function payload(array $extra=[]):array{return array_replace(['server_url'=>'http://192.168.1.10:8000','create_readers'=>true,'rides'=>$this->rows()],$extra);}
 public function test_setup_form_is_read_only_and_does_not_guess_rates():void {
  $this->actingAs($this->admin)->get('/rides/setup-25')->assertOk()->assertSee('25 rides. One QR wallet.')->assertSee('name="rides[24][points_per_play]"',false);
  $this->assertDatabaseCount('rides',0);$this->assertDatabaseCount('machines',0);
 }
 public function test_cashier_and_staff_cannot_provision_camera_readers():void {
  foreach(['cashier','staff'] as $role){$u=User::create(['name'=>$role,'email'=>$role.'@example.test','password'=>'Password123!','role'=>$role,'active'=>true]);$this->actingAs($u)->withSession(['password_stamp'=>hash('sha256',$u->password)])->get('/rides/setup-25')->assertForbidden();$this->post('/rides/setup-25',$this->payload())->assertForbidden();}
  $this->assertDatabaseCount('rides',0);
 }
 public function test_admin_saves_25_rates_and_gets_a_private_firmware_zip():void {
  $r=$this->actingAs($this->admin)->post('/rides/setup-25',$this->payload());
  $r->assertOk()->assertHeader('Content-Type','application/zip')->assertHeader('Cache-Control','no-store, private');
  $this->assertSame("PK\x03\x04",substr($r->getContent(),0,4));
  $this->assertDatabaseCount('rides',25);$this->assertDatabaseCount('machines',25);
  $this->assertSame(0,Machine::where('enabled',true)->count());$this->assertSame(25,Machine::distinct()->count('token_hash'));
  foreach(Ride::with('machines')->orderBy('id')->get() as $i=>$ride){$this->assertSame($i+1,(int)$ride->points_per_play);$this->assertSame('ESP-'.$ride->code,$ride->machines->first()->serial);$this->assertSame(64,strlen($ride->machines->first()->token_hash));}
 }
 public function test_setup_can_save_rates_without_creating_readers():void {
  $this->actingAs($this->admin)->post('/rides/setup-25',$this->payload(['create_readers'=>false]))->assertOk();
  $this->assertDatabaseCount('rides',25);$this->assertDatabaseCount('machines',0);
 }
 public function test_blank_or_zero_points_reject_the_whole_batch():void {
  $rows=$this->rows();$rows[24]['points_per_play']=0;
  $this->actingAs($this->admin)->post('/rides/setup-25',$this->payload(['rides'=>$rows]))->assertSessionHasErrors('rides.24.points_per_play');
  $this->assertDatabaseCount('rides',0);$this->assertDatabaseCount('machines',0);
 }
 public function test_normalized_duplicate_codes_reject_the_whole_batch():void {
  $rows=$this->rows();$rows[24]['code']='ride-01';
  $this->actingAs($this->admin)->post('/rides/setup-25',$this->payload(['rides'=>$rows]))->assertSessionHasErrors('rides.24.code');
  $this->assertDatabaseCount('rides',0);
 }
 public function test_collision_with_an_existing_reader_rolls_back_all_new_rides():void {
  $ride=Ride::create(['name'=>'Existing','code'=>'EXISTING','points_per_play'=>50,'pulse_ms'=>250,'cooldown_seconds'=>5,'active'=>true]);
  Machine::create(['name'=>'Existing reader','serial'=>'ESP-RIDE-25','ride_id'=>$ride->id,'token_hash'=>str_repeat('b',64),'enabled'=>false]);
  $this->actingAs($this->admin)->post('/rides/setup-25',$this->payload())->assertSessionHasErrors('create_readers');
  $this->assertDatabaseCount('rides',1);$this->assertDatabaseCount('machines',1);$this->assertDatabaseCount('audit_logs',0);
 }
 public function test_existing_reader_and_pending_transaction_are_retained_when_rates_change():void {
  $ride=Ride::create($this->rows()[0]);$hash=hash('sha256',str_repeat('a',64));
  $m=Machine::create(['name'=>'Existing reader','serial'=>'ESP-RIDE-01','ride_id'=>$ride->id,'token_hash'=>$hash,'enabled'=>true]);
  $p=ParkPass::create(['credential'=>'QR-'.str_repeat('A',32),'balance'=>100,'status'=>'active','expires_at'=>now()->addDay()]);
  $this->withHeader('Authorization','Bearer '.str_repeat('a',64))->postJson('/api/machine/redeem',['credential'=>$p->credential,'request_id'=>'camera-old-request'])->assertOk();
  $rows=$this->rows();$rows[0]['id']=$ride->id;$rows[0]['points_per_play']=99;
  $this->actingAs($this->admin)->post('/rides/setup-25',$this->payload(['rides'=>$rows]))->assertOk();
  $this->assertSame($hash,$m->fresh()->token_hash);$this->assertTrue($m->fresh()->enabled);
  $this->assertSame(1,(int)Redemption::first()->points);$this->assertSame('approved',Redemption::first()->status);$this->assertSame(99,(int)$ride->fresh()->points_per_play);
  $this->assertDatabaseCount('machines',25);
 }
 public function test_server_address_rejects_localhost_and_public_plain_http():void {
  foreach(['http://localhost:8000','http://127.0.0.1:8000','http://example.com','https://user:password@example.com','https://example.com/?x=1'] as $url){$this->actingAs($this->admin)->post('/rides/setup-25',$this->payload(['server_url'=>$url]))->assertSessionHasErrors('server_url');}
  $this->assertDatabaseCount('rides',0);
 }
 public function test_per_reader_configuration_is_downloaded_with_its_verified_token():void {
  $ride=Ride::create($this->rows()[0]);$token=str_repeat('a',64);
  $m=Machine::create(['name'=>'Reader','serial'=>'ESP-RIDE-01','ride_id'=>$ride->id,'token_hash'=>hash('sha256',$token),'enabled'=>false]);
  $r=$this->actingAs($this->admin)->post('/machines/'.$m->id.'/camera-config',['machine_token'=>$token,'server_url'=>'https://erp.example.test']);
  $r->assertOk()->assertHeader('Content-Disposition','attachment; filename="Config.h"')->assertSee($token,false)->assertSee('EXPECTED_MACHINE_ID = '.$m->id,false)->assertSee('ENABLE_PULSE_OUTPUT = false',false)->assertSee('ESP-RIDE-01',false);
  $this->post('/machines/'.$m->id.'/camera-config',['machine_token'=>str_repeat('b',64),'server_url'=>'https://erp.example.test'])->assertSessionHasErrors('machine_token');
 }
 public function test_machine_api_v2_includes_identity_and_is_not_cacheable():void {
  $ride=Ride::create($this->rows()[0]);$token=str_repeat('a',64);
  $m=Machine::create(['name'=>'Reader','serial'=>'ESP-RIDE-01','ride_id'=>$ride->id,'token_hash'=>hash('sha256',$token),'enabled'=>true]);
  $p=ParkPass::create(['credential'=>'QR-'.str_repeat('A',32),'balance'=>10,'status'=>'active','expires_at'=>now()->addDay()]);
  $this->withHeader('Authorization','Bearer '.$token)->getJson('/api/machine/config')->assertOk()->assertJsonPath('api_version',2)->assertJsonPath('ride.code','RIDE-01')->assertHeader('Cache-Control','no-store, private');
  $this->postJson('/api/machine/redeem',['credential'=>$p->credential,'request_id'=>'camera-request-123'])->assertOk()->assertJsonPath('machine_id',$m->id)->assertJsonPath('serial','ESP-RIDE-01')->assertJsonPath('points_deducted',1)->assertHeader('Cache-Control','no-store, private');
 }
}
