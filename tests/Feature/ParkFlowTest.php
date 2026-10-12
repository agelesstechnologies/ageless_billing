<?php
namespace Tests\Feature;
use App\Models\{User,Setting,Ride,Machine,ParkPass,Package,Sale,Redemption,PointEntry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class ParkFlowTest extends TestCase {
 use RefreshDatabase;
 private User $admin;private Ride $ride;private Machine $reader;private ParkPass $pass;private string $token;
 protected function setUp():void{
  parent::setUp();Setting::create(['data'=>Setting::defaults()]);
  $this->admin=User::create(['name'=>'Test Admin','email'=>'admin@example.test','password'=>'TestPassword123!','role'=>'admin','active'=>true]);
  $this->ride=Ride::create(['name'=>'Test Ride','code'=>'TEST','points_per_play'=>100,'pulse_ms'=>250,'cooldown_seconds'=>5,'active'=>true]);
  $this->token=str_repeat('a',64);$this->reader=Machine::create(['name'=>'Test Reader','serial'=>'ESP-TEST','ride_id'=>$this->ride->id,'token_hash'=>hash('sha256',$this->token),'enabled'=>true]);
  $this->pass=ParkPass::create(['credential'=>'QR-'.str_repeat('A',32),'customer_name'=>'Test Guest','balance'=>200,'expires_at'=>now()->addDay(),'status'=>'active']);
 }
 private function scan(string $id='scan-00000001',array $extra=[]){return $this->withHeader('Authorization','Bearer '.$this->token)->postJson('/api/machine/redeem',array_replace(['credential'=>$this->pass->credential,'request_id'=>$id],$extra));}
 private function ack(string $id='scan-00000001',string $outcome='pulsed'){return $this->withHeader('Authorization','Bearer '.$this->token)->postJson('/api/machine/transactions/'.$id.'/ack',['outcome'=>$outcome]);}
 private function package():Package{return Package::create(['name'=>'Test Package','kind'=>'package','price_paise'=>50000,'points'=>600,'validity_hours'=>24,'active'=>true]);}
 private function checkout(int $packageId,array $extra=[]):array{return array_replace(['request_id'=>(string)Str::uuid(),'customer_name'=>'New Guest','items'=>[['package_id'=>$packageId,'quantity'=>1]],'discount'=>'0','payment_method'=>'cash'],$extra);}
 public function test_private_pages_require_login():void{$this->get('/')->assertRedirect('/login');$this->get('/pos')->assertRedirect('/login');}
 public function test_authentication_and_role_permissions():void{$cashier=User::create(['name'=>'Cashier','email'=>'cash@example.test','password'=>'TestPassword123!','role'=>'cashier','active'=>true]);$this->actingAs($cashier)->get('/settings')->assertForbidden();$this->actingAs($cashier)->get('/pos')->assertOk();}
 public function test_disabled_staff_cannot_continue():void{$this->admin->update(['active'=>false]);$this->actingAs($this->admin)->get('/pos')->assertRedirect('/login');}
 public function test_dashboard_and_admin_screens_render():void{foreach(['/','/pos','/packages','/packages/create','/rides','/rides/create','/machines','/machines/create','/settings','/users','/users/create','/sales','/passes','/transactions','/audit'] as $url)$this->actingAs($this->admin)->get($url)->assertOk();}
 public function test_unknown_token_is_rejected():void{$this->withHeader('Authorization','Bearer bad')->getJson('/api/machine/config')->assertUnauthorized();}
 public function test_points_deduct_once_for_same_request():void{$this->scan()->assertOk()->assertJson(['allow_pulse'=>true,'points_deducted'=>100,'balance_after'=>100]);$this->scan()->assertOk()->assertJson(['replay'=>true,'balance_after'=>100]);$this->assertSame(100,$this->pass->fresh()->balance);$this->assertDatabaseCount('point_entries',1);}
 public function test_reusing_request_for_another_qr_is_rejected():void{$this->scan();$this->scan('scan-00000001',['credential'=>'QR-'.str_repeat('B',32)])->assertStatus(409)->assertJson(['code'=>'REQUEST_CONFLICT']);}
 public function test_cost_cannot_be_overridden_by_device():void{$this->scan('scan-00000001',['points'=>1,'ride_id'=>999,'pulse_ms'=>1000])->assertJson(['points_deducted'=>100,'pulse_ms'=>250]);}
 public function test_low_balance_cannot_go_negative():void{$this->pass->update(['balance'=>99]);$this->scan()->assertStatus(422)->assertJson(['code'=>'INSUFFICIENT_POINTS','allow_pulse'=>false]);$this->assertSame(99,$this->pass->fresh()->balance);}
 public function test_expired_pass_is_denied():void{$this->pass->update(['expires_at'=>now()->subSecond()]);$this->scan()->assertStatus(422)->assertJson(['code'=>'PASS_EXPIRED']);}
 public function test_blocked_pass_is_denied():void{$this->pass->update(['status'=>'blocked']);$this->scan()->assertStatus(422)->assertJson(['code'=>'PASS_BLOCKED']);}
 public function test_disabled_machine_is_denied():void{$this->reader->update(['enabled'=>false]);$this->scan()->assertStatus(422)->assertJson(['code'=>'MACHINE_DISABLED']);}
 public function test_removed_ride_is_denied():void{$this->ride->delete();$this->scan()->assertStatus(422)->assertJson(['code'=>'RIDE_DISABLED']);}
 public function test_pending_play_blocks_next_request():void{$this->scan();$this->scan('scan-00000002')->assertStatus(422)->assertJson(['code'=>'MACHINE_PENDING']);$this->assertSame(100,$this->pass->fresh()->balance);}
 public function test_acknowledgements_are_idempotent():void{$this->scan();$this->ack()->assertOk()->assertJson(['status'=>'completed','allow_pulse'=>false]);$this->ack()->assertOk()->assertJson(['status'=>'completed']);$this->scan()->assertJson(['allow_pulse'=>false]);$this->assertSame(100,$this->pass->fresh()->balance);}
 public function test_cooldown_then_new_play():void{$this->scan();$this->ack();$this->scan('scan-00000002')->assertStatus(422)->assertJson(['code'=>'COOLDOWN']);$this->travel(6)->seconds();$this->scan('scan-00000003')->assertOk()->assertJson(['balance_after'=>0]);}
 public function test_approval_expires_into_review_not_refund():void{$this->scan();$this->travel(31)->seconds();$this->scan()->assertStatus(409)->assertJson(['status'=>'uncertain','allow_pulse'=>false]);$this->assertSame(100,$this->pass->fresh()->balance);}
 public function test_uncertain_ack_stops_replay():void{$this->scan();$this->ack('scan-00000001','uncertain')->assertStatus(409)->assertJson(['status'=>'uncertain']);$this->scan()->assertJson(['allow_pulse'=>false]);}
 public function test_manual_refund_is_recorded_once():void{$this->scan();$this->travel(31)->seconds();$this->reader->update(['enabled'=>false]);$tx=Redemption::first();$data=['action'=>'refund','note'=>'Staff verified no pulse; device powered off.','reader_powered_off'=>'1'];$this->actingAs($this->admin)->post('/transactions/'.$tx->id.'/resolve',$data)->assertRedirect();$this->assertSame(200,$this->pass->fresh()->balance);$this->assertSame('reversed',$tx->fresh()->status);$this->post('/transactions/'.$tx->id.'/resolve',$data)->assertSessionHasErrors();$this->assertSame(200,$this->pass->fresh()->balance);}
 public function test_checkout_issues_one_qr_and_is_idempotent():void{$data=$this->checkout($this->package()->id);$this->actingAs($this->admin)->post('/sales',$data)->assertRedirect();$sale=Sale::firstOrFail();$this->assertSame(600,$sale->pass->balance);$this->post('/sales',$data)->assertRedirect();$this->assertDatabaseCount('sales',1);$this->get('/sales/'.$sale->id)->assertOk()->assertSee('<svg',false);}
 public function test_tax_is_calculated_on_server():void{$settings=Setting::current();$settings['tax_rate']='18';Setting::find(1)->update(['data'=>$settings]);$this->actingAs($this->admin)->post('/sales',$this->checkout($this->package()->id,['discount'=>'50','total_paise'=>1]))->assertRedirect();$sale=Sale::first();$this->assertEquals(53100,$sale->total_paise);$this->assertEquals(8100,$sale->tax_paise);}
 public function test_cashier_cannot_apply_discount():void{$cashier=User::create(['name'=>'Cashier','email'=>'cash@example.test','password'=>'TestPassword123!','role'=>'cashier','active'=>true]);$this->actingAs($cashier)->post('/sales',$this->checkout($this->package()->id,['discount'=>'1']))->assertSessionHasErrors('discount');$this->assertDatabaseCount('sales',0);}
 public function test_topup_keeps_same_wallet():void{$this->actingAs($this->admin)->post('/sales',$this->checkout($this->package()->id,['existing_credential'=>$this->pass->credential]))->assertRedirect();$this->assertSame(800,$this->pass->fresh()->balance);$this->assertDatabaseCount('park_passes',1);$this->assertSame('topup',Sale::first()->kind);}
 public function test_old_receipt_values_survive_package_edits():void{$p=$this->package();$this->actingAs($this->admin)->post('/sales',$this->checkout($p->id));$p->update(['name'=>'Changed','price_paise'=>1,'points'=>1]);$s=Sale::first();$this->assertSame('Test Package',$s->items->first()->name);$this->assertEquals(50000,$s->items->first()->unit_price_paise);}
 public function test_unused_bill_void_blocks_wallet():void{$this->actingAs($this->admin)->post('/sales',$this->checkout($this->package()->id));$s=Sale::first();$this->post('/sales/'.$s->id.'/void',['reason'=>'Guest cancelled before using any rides.'])->assertRedirect();$this->assertSame('voided',$s->fresh()->status);$this->assertSame(0,$s->pass->fresh()->balance);$this->assertSame('blocked',$s->pass->fresh()->status);}
 public function test_archived_package_cannot_be_sold():void{$p=$this->package();$p->delete();$this->actingAs($this->admin)->post('/sales',$this->checkout($p->id))->assertSessionHasErrors('items');$this->assertDatabaseCount('sales',0);}
 public function test_receipt_qr_is_only_visible_to_staff():void{$this->actingAs($this->admin)->get('/passes/'.$this->pass->id)->assertOk()->assertSee($this->pass->credential);$this->post('/logout');$this->get('/passes/'.$this->pass->id)->assertRedirect('/login');}
}
