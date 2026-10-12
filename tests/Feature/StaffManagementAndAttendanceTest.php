<?php
namespace Tests\Feature;

use App\Models\{AttendancePunch, AttendanceSession, Setting, User, WorkSite};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffManagementAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private WorkSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 09:00:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 09:00:00', 'UTC'));

        Setting::create(['data' => Setting::defaults()]);

        $this->site = WorkSite::create([
            'name' => 'Ageless Adventure Park',
            'timezone' => 'Asia/Kolkata',
            'latitude' => 10.0000000,
            'longitude' => 76.3000000,
            'radius_m' => 1000,
            'max_accuracy_m' => 200,
            'enforce_geofence' => false,
            'active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@agelesspark.com',
            'password' => 'AdminPass123!',
            'role' => 'admin',
            'active' => true,
            'trade' => 'Management',
            'designation' => 'Management',
            'phone' => '+91 99999 00001',
            'work_site_id' => $this->site->id,
            'attendance_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function signIn(User $user): static
    {
        $this->actingAs($user);
        $this->withSession(['password_stamp' => hash('sha256', $user->password)]);
        return $this;
    }

    public function test_admin_can_view_staff_management_list(): void
    {
        $this->signIn($this->admin)->get('/users')->assertOk()
            ->assertSee('Staff Management')
            ->assertSee('Employee Directory');

        $this->signIn($this->admin)->get('/staff-management')->assertOk()
            ->assertSee('Staff Management');
    }

    public function test_admin_creates_employee_with_complete_profile(): void
    {
        $response = $this->signIn($this->admin)->post('/users', [
            'name' => 'Rahul Sharma',
            'phone' => '+91 98765 43210',
            'trade' => 'Ride Operator',
            'email' => 'rahul.sharma@agelesspark.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'staff',
            'active' => '1',
            'work_site_id' => $this->site->id,
            'attendance_enabled' => '1',
        ]);

        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'name' => 'Rahul Sharma',
            'email' => 'rahul.sharma@agelesspark.com',
            'phone' => '+91 98765 43210',
            'trade' => 'Ride Operator',
            'role' => 'staff',
            'active' => 1,
            'attendance_enabled' => 1,
        ]);

        $user = User::where('email', 'rahul.sharma@agelesspark.com')->firstOrFail();
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
        $this->assertEquals('Ride Operator', $user->jobRole());
    }

    public function test_admin_saving_employee_without_a_site_does_not_assign_one(): void
    {
        $this->signIn($this->admin)->post('/users', [
            'name' => 'No Site Person', 'email' => 'nosite@agelesspark.com',
            'password' => 'SecurePass123!', 'password_confirmation' => 'SecurePass123!',
            'role' => 'staff', 'active' => '1', 'work_site_id' => '', 'attendance_enabled' => '0',
        ])->assertRedirect('/users');

        $user = User::where('email', 'nosite@agelesspark.com')->firstOrFail();
        $this->assertNull($user->work_site_id);
        $this->assertFalse($user->attendance_enabled);
    }

    public function test_staff_home_shows_notice_instead_of_punch_buttons_when_attendance_is_off(): void
    {
        $employee = User::create([
            'name' => 'Off Person', 'email' => 'off@agelesspark.com', 'password' => 'Password123!',
            'role' => 'staff', 'active' => true, 'attendance_enabled' => false, 'work_site_id' => $this->site->id,
        ]);

        $this->signIn($employee)->get('/staff')->assertOk()
            ->assertSee('Attendance is not enabled for your account')
            ->assertDontSee('data-punch-in', false);
        $this->assertFalse($employee->fresh()->attendance_enabled);
    }

    public function test_admin_can_edit_employee_and_reset_password(): void
    {
        $employee = User::create([
            'name' => 'Ajay Verma',
            'email' => 'ajay@agelesspark.com',
            'password' => 'OldPassword123!',
            'role' => 'staff',
            'active' => true,
            'phone' => '+91 98111 22233',
            'trade' => 'Technician',
            'work_site_id' => $this->site->id,
            'attendance_enabled' => true,
        ]);

        $this->signIn($this->admin)->put('/users/' . $employee->id, [
            'name' => 'Ajay Verma Senior',
            'email' => 'ajay@agelesspark.com',
            'phone' => '+91 98111 99999',
            'trade' => 'Senior Technician',
            'password' => 'NewResetPassword123!',
            'password_confirmation' => 'NewResetPassword123!',
            'role' => 'staff',
            'active' => '1',
            'work_site_id' => $this->site->id,
            'attendance_enabled' => '1',
        ])->assertRedirect('/users');

        $employee->refresh();
        $this->assertEquals('Ajay Verma Senior', $employee->name);
        $this->assertEquals('+91 98111 99999', $employee->phone);
        $this->assertEquals('Senior Technician', $employee->trade);
        $this->assertTrue(Hash::check('NewResetPassword123!', $employee->password));
    }

    public function test_admin_can_activate_and_deactivate_account(): void
    {
        $employee = User::create([
            'name' => 'Kiran Das',
            'email' => 'kiran@agelesspark.com',
            'password' => 'Pass123456!',
            'role' => 'staff',
            'active' => true,
            'phone' => '+91 98444 55566',
            'trade' => 'Electrician',
            'work_site_id' => $this->site->id,
            'attendance_enabled' => true,
        ]);

        // Toggle to inactive
        $this->signIn($this->admin)->post('/users/' . $employee->id . '/toggle-status')
            ->assertRedirect('/users');

        $employee->refresh();
        $this->assertFalse($employee->active);

        // Inactive user cannot log in
        $this->post('/login', [
            'email' => 'kiran@agelesspark.com',
            'password' => 'Pass123456!',
        ])->assertSessionHasErrors('email');

        // Toggle back to active
        $this->signIn($this->admin)->post('/users/' . $employee->id . '/toggle-status')
            ->assertRedirect('/users');

        $employee->refresh();
        $this->assertTrue($employee->active);
    }

    public function test_complete_attendance_flow(): void
    {
        // Step 1: Admin creates employee
        $employee = User::create([
            'name' => 'Pooja Nair',
            'email' => 'pooja@agelesspark.com',
            'password' => 'PoojaPassword123!',
            'role' => 'staff',
            'active' => true,
            'phone' => '+91 94470 12345',
            'trade' => 'Ride Operator',
            'work_site_id' => $this->site->id,
            'attendance_enabled' => true,
        ]);

        // Step 2: Employee logs in and views mobile dashboard
        $this->post('/login', [
            'email' => 'pooja@agelesspark.com',
            'password' => 'PoojaPassword123!',
        ])->assertRedirect('/staff');

        $this->signIn($employee)->get('/staff')->assertOk()
            ->assertSee('Pooja Nair')
            ->assertSee('Ride Operator')
            ->assertSee('Punch In')
            ->assertSee('Punch Out')
            ->assertSee('Not Punched In');

        // Step 3: Employee punches in with GPS location
        $inUuid = (string) Str::uuid();
        $punchInResponse = $this->signIn($employee)->postJson('/staff/punch', [
            'action' => 'in',
            'request_id' => $inUuid,
            'latitude' => 10.0012345,
            'longitude' => 76.3005432,
            'accuracy_m' => 12.5,
            'note' => 'Shift start at Cyclone Coaster',
        ]);

        $punchInResponse->assertOk()
            ->assertJson([
                'ok' => true,
                'action' => 'in',
            ]);

        $session = AttendanceSession::where('user_id', $employee->id)->whereNull('ended_at')->firstOrFail();
        $this->assertEquals($employee->id, $session->open_user_id);

        $punchIn = AttendancePunch::where('attendance_session_id', $session->id)->where('type', 'in')->firstOrFail();
        $this->assertEquals(10.0012345, $punchIn->latitude);
        $this->assertEquals(76.3005432, $punchIn->longitude);
        $this->assertEquals(12.5, $punchIn->accuracy_m);

        // Employee mobile dashboard now reflects On Duty status
        $this->signIn($employee)->get('/staff')->assertOk()
            ->assertSee('Punched In · On Duty')
            ->assertSee('Shift already open');

        // Step 4: Admin sees the record with location on the attendance dashboard
        $this->signIn($this->admin)->get('/attendance')->assertOk()
            ->assertSee('Pooja Nair')
            ->assertSee('+91 94470 12345')
            ->assertSee('Ride Operator')
            ->assertSee('10.00123')
            ->assertSee('76.30054')
            ->assertSee('View Location')
            ->assertSee('On duty');

        // Verify duplicate punch-in is rejected
        $this->signIn($employee)->postJson('/staff/punch', [
            'action' => 'in',
            'request_id' => (string) Str::uuid(),
            'latitude' => 10.0012345,
            'longitude' => 76.3005432,
            'accuracy_m' => 12.5,
        ])->assertStatus(422);

        // Advance time by 4 hours
        $next = CarbonImmutable::now('UTC')->addHours(4);
        Carbon::setTestNow($next);
        CarbonImmutable::setTestNow($next);

        // Step 5: Employee punches out with a FRESH location
        $outUuid = (string) Str::uuid();
        $punchOutResponse = $this->signIn($employee)->postJson('/staff/punch', [
            'action' => 'out',
            'session_id' => $session->id,
            'request_id' => $outUuid,
            'latitude' => 10.0028888, // Fresh distinct coordinates
            'longitude' => 76.3019999,
            'accuracy_m' => 8.0,
            'note' => 'Shift ended, all checks completed',
        ]);

        $punchOutResponse->assertOk()
            ->assertJson([
                'ok' => true,
                'action' => 'out',
            ]);

        $session->refresh();
        $this->assertNotNull($session->ended_at);
        $this->assertNull($session->open_user_id);

        $punchOut = AttendancePunch::where('attendance_session_id', $session->id)->where('type', 'out')->firstOrFail();
        $this->assertEquals(10.0028888, $punchOut->latitude);
        $this->assertEquals(76.3019999, $punchOut->longitude);
        $this->assertNotEquals($punchIn->latitude, $punchOut->latitude);

        // Step 6: Admin sees completed record with duration and fresh punch-out location
        $adminView = $this->signIn($this->admin)->get('/attendance');
        $adminView->assertOk()
            ->assertSee('Pooja Nair')
            ->assertSee('4h 00m')
            ->assertSee('Completed')
            ->assertSee('10.00289')
            ->assertSee('76.30200')
            ->assertSee('View Location');
    }

    public function test_access_control_restrictions(): void
    {
        $employee = User::create([
            'name' => 'Vikram Singh',
            'email' => 'vikram@agelesspark.com',
            'password' => 'VikramPass123!',
            'role' => 'staff',
            'active' => true,
            'trade' => 'Security',
            'work_site_id' => $this->site->id,
            'attendance_enabled' => true,
        ]);

        // Staff cannot access admin dashboard, staff management, or full attendance
        $this->signIn($employee)->get('/users')->assertForbidden();
        $this->signIn($employee)->get('/attendance')->assertForbidden();
        $this->signIn($employee)->get('/crm')->assertForbidden();
        $this->signIn($employee)->get('/settings')->assertForbidden();

        // Staff can only access their own portal
        $this->signIn($employee)->get('/staff')->assertOk();
        $this->signIn($employee)->get('/staff/history')->assertOk();
    }

    public function test_admin_can_search_and_filter_attendance(): void
    {
        $emp1 = User::create([
            'name' => 'Anil Kapoor',
            'email' => 'anil@agelesspark.com',
            'password' => 'Pass123456!',
            'role' => 'staff',
            'active' => true,
            'phone' => '+91 91234 56789',
            'trade' => 'Housekeeping',
            'work_site_id' => $this->site->id,
            'attendance_enabled' => true,
        ]);

        $this->signIn($emp1)->postJson('/staff/punch', [
            'action' => 'in',
            'request_id' => (string) Str::uuid(),
            'latitude' => 10.0,
            'longitude' => 76.3,
            'accuracy_m' => 10,
        ])->assertOk();

        // Search by name
        $this->signIn($this->admin)->get('/attendance?search=Anil')
            ->assertOk()
            ->assertSee('Anil Kapoor');

        // Search by phone
        $this->signIn($this->admin)->get('/attendance?search=91234')
            ->assertOk()
            ->assertSee('Anil Kapoor');

        // Filter by trade
        $this->signIn($this->admin)->get('/attendance?trade=Housekeeping')
            ->assertOk()
            ->assertSee('Anil Kapoor');

        // Filter by different trade should not show Anil in the records table
        $this->signIn($this->admin)->get('/attendance?trade=Ride+Operator')
            ->assertOk()
            ->assertSee('No attendance records in this selection');
    }
}
