<?php
namespace Tests\Feature;

use App\Models\{Setting, User, WorkSite};
use App\Rules\StrongPassword;
use Database\Seeders\DemoBillingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Validator};
use Tests\TestCase;

class PasswordPolicyAndLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const SHORT = 'Abcdefgh1jk';   // 11 characters, otherwise valid
    private const OK = 'Abcdefgh1jkl';     // 12 characters

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['data' => Setting::defaults()]);
        $this->admin = $this->person('admin', 'admin@example.test');
    }

    private function person(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $email, 'password' => 'OriginalPassword123!',
            'role' => $role, 'active' => true,
        ]);
    }

    private function signIn(User $user): static
    {
        $this->actingAs($user);
        $this->withSession(['password_stamp' => hash('sha256', $user->password)]);
        return $this;
    }

    public function test_eleven_characters_is_rejected_and_twelve_accepted_by_the_shared_rule(): void
    {
        $check = fn(string $p) => Validator::make(['password' => $p], ['password' => [new StrongPassword]])->passes();
        $this->assertFalse($check(self::SHORT));
        $this->assertTrue($check(self::OK));
        $this->assertTrue($check(StrongPassword::generate()));
        $this->assertSame(16, strlen(StrongPassword::generate()));
    }

    public function test_admin_user_creation_requires_twelve_characters(): void
    {
        $form = fn(string $p, string $email) => [
            'name' => 'New Person', 'email' => $email, 'role' => 'staff', 'active' => '1',
            'password' => $p, 'password_confirmation' => $p,
        ];

        $this->signIn($this->admin)->post('/users', $form(self::SHORT, 'short@example.test'))->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'short@example.test']);

        $this->signIn($this->admin)->post('/users', $form(self::OK, 'ok@example.test'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::OK, User::where('email', 'ok@example.test')->firstOrFail()->password));
    }

    public function test_admin_edit_requires_twelve_characters_when_a_password_is_given(): void
    {
        $employee = $this->person('staff', 'edit@example.test');
        $form = fn(string $p) => [
            'name' => 'Edited', 'email' => 'edit@example.test', 'role' => 'staff', 'active' => '1',
            'password' => $p, 'password_confirmation' => $p,
        ];

        $this->signIn($this->admin)->put('/users/'.$employee->id, $form(self::SHORT))->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('OriginalPassword123!', $employee->fresh()->password));

        // A blank password still means "leave it unchanged".
        $this->signIn($this->admin)->put('/users/'.$employee->id, $form(''))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('OriginalPassword123!', $employee->fresh()->password));

        $this->signIn($this->admin)->put('/users/'.$employee->id, $form(self::OK))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::OK, $employee->fresh()->password));
    }

    public function test_counter_creation_requires_twelve_characters(): void
    {
        $form = fn(string $p) => [
            'name' => 'Counter 1', 'employee_code' => 'COUNTER1', 'password' => $p, 'password_confirmation' => $p,
        ];
        $this->signIn($this->admin)->post('/counters', $form(self::SHORT))->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['employee_code' => 'COUNTER1']);
        $this->signIn($this->admin)->post('/counters', $form(self::OK))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['employee_code' => 'COUNTER1']);
    }

    public function test_self_change_requires_twelve_characters(): void
    {
        $staff = User::create([
            'name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'OriginalPassword123!',
            'role' => 'staff', 'active' => true,
            'work_site_id' => WorkSite::create(['name' => 'Park', 'timezone' => 'Asia/Kolkata', 'radius_m' => 200, 'max_accuracy_m' => 100, 'enforce_geofence' => false, 'active' => true])->id,
            'attendance_enabled' => true,
        ]);
        $form = fn(string $p) => ['current_password' => 'OriginalPassword123!', 'password' => $p, 'password_confirmation' => $p];

        $this->signIn($staff)->put('/staff/password', $form(self::SHORT))->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('OriginalPassword123!', $staff->fresh()->password));

        $this->signIn($staff)->put('/staff/password', $form(self::OK))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::OK, $staff->fresh()->password));
    }

    public function test_demo_seeder_creates_accounts_that_meet_the_policy(): void
    {
        $this->seed(DemoBillingSeeder::class);

        $cashier = User::where('email', 'cashier@example.com')->firstOrFail();
        $this->assertFalse(Hash::check('password', $cashier->password), 'the old fixed demo password must be gone');
    }

    public function test_login_is_limited_per_ip_across_different_emails(): void
    {
        // Every attempt uses a different email, so the per-email limit (5) is never reached;
        // only the per-IP limit (20) can stop this.
        for ($i = 1; $i <= 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
                ->post('/login', ['email' => "user$i@example.test", 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->post('/login', ['email' => 'user21@example.test', 'password' => 'wrong-password'])
            ->assertStatus(429);

        // A different address is unaffected.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->post('/login', ['email' => 'user1@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
    }

    public function test_per_email_login_limit_still_applies(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['email' => 'admin@example.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'OriginalPassword123!'])->assertStatus(429);
    }
}
