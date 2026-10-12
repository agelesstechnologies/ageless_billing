<?php
namespace Tests\Feature;

use App\Models\{AttendanceCorrection, AttendancePunch, AttendanceSession, Setting, User, WorkSite};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Integration acceptance suite: needs Composer dependencies and a PDO SQLite driver.
 * Included in the passing 91-test SQLite suite for the 9 October database update.
 * Serial tests do not establish real MySQL multi-connection race safety.
 */
class AttendanceFlowTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $staff;
    private WorkSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 12:00:00', 'UTC'));
        Setting::create(['data' => Setting::defaults()]);
        $this->site = WorkSite::create([
            'name' => 'Test park', 'timezone' => 'Asia/Kolkata',
            'latitude' => 10.0, 'longitude' => 76.3, 'radius_m' => 200,
            'max_accuracy_m' => 100, 'enforce_geofence' => true, 'active' => true,
        ]);
        $this->admin = $this->person('admin', 'admin@example.test');
        $this->staff = $this->person('staff', 'staff@example.test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function person(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role).' Test', 'email' => $email,
            'password' => 'TestPassword123!', 'role' => $role, 'active' => true,
            'work_site_id' => $this->site->id, 'attendance_enabled' => true,
        ]);
    }

    private function signIn(User $user): static
    {
        $this->actingAs($user);
        $this->withSession(['password_stamp' => hash('sha256', $user->password)]);
        return $this;
    }

    private function payload(array $extra = []): array
    {
        return array_replace([
            'action' => 'in', 'request_id' => (string) Str::uuid(),
            'latitude' => 10.0001, 'longitude' => 76.3001,
            'accuracy_m' => 12, 'note' => 'Test attendance punch',
        ], $extra);
    }

    private function punch(array $extra = [], ?User $user = null): TestResponse
    {
        return $this->signIn($user ?? $this->staff)->postJson('/staff/punch', $this->payload($extra));
    }

    private function advance(int $hours = 1): void
    {
        $next = CarbonImmutable::now('UTC')->addHours($hours);
        Carbon::setTestNow($next); CarbonImmutable::setTestNow($next);
    }

    private function openShift(): AttendanceSession
    {
        $this->punch()->assertOk();
        return AttendanceSession::where('user_id', $this->staff->id)->firstOrFail();
    }

    private function correction(?AttendanceSession $session = null, string $day = '2026-10-05'): AttendanceCorrection
    {
        return AttendanceCorrection::create([
            'user_id' => $this->staff->id, 'work_site_id' => $this->site->id,
            'attendance_session_id' => $session?->id,
            'work_date' => $session?->work_date ?? $day,
            'timezone' => 'Asia/Kolkata', 'reason' => 'Please check a missing device punch.',
        ]);
    }

    private function review(AttendanceCorrection $request, array $extra = []): TestResponse
    {
        return $this->signIn($this->admin)->post('/crm/requests/'.$request->id, array_replace([
            'decision' => 'approved', 'started_at' => $request->work_date.'T09:00',
            'ended_at' => $request->work_date.'T17:00',
            'review_note' => 'Verified with the supervisor and staff register.',
        ], $extra));
    }

    public function test_staff_login_ignores_billing_intended_url(): void
    {
        $this->withSession(['url.intended' => url('/pos')])->post('/login', [
            'email' => $this->staff->email, 'password' => 'TestPassword123!',
        ])->assertRedirect('/staff');
    }

    public function test_staff_root_redirects_to_mobile_portal(): void
    {
        $this->signIn($this->staff)->get('/')->assertRedirect('/staff');
    }

    public function test_staff_pages_render_without_admin_workspace_access(): void
    {
        foreach (['/staff', '/staff/history', '/staff/requests', '/staff/profile'] as $path) {
            $this->signIn($this->staff)->get($path)->assertOk();
        }
    }

    public function test_opening_staff_home_does_not_enable_attendance_or_assign_a_site(): void
    {
        $this->staff->update(['attendance_enabled' => false, 'work_site_id' => null]);

        $this->signIn($this->staff)->get('/staff')->assertOk();

        $fresh = $this->staff->fresh();
        $this->assertFalse($fresh->attendance_enabled);
        $this->assertNull($fresh->work_site_id);
    }

    public function test_staff_cannot_open_billing_or_administration(): void
    {
        foreach (['/pos', '/sales', '/passes', '/crm', '/crm/export', '/crm/requests', '/sites', '/users', '/settings', '/audit', '/packages', '/rides', '/machines', '/transactions'] as $path) {
            $this->signIn($this->staff)->get($path)->assertForbidden();
        }
        $this->signIn($this->staff)->post('/sales', [])->assertForbidden();
    }

    public function test_cashier_keeps_billing_but_cannot_read_site_crm(): void
    {
        $cashier = $this->person('cashier', 'cashier@example.test');
        $this->signIn($cashier)->get('/pos')->assertOk();
        $this->signIn($cashier)->get('/crm')->assertForbidden();
    }

    public function test_administrator_crm_and_configuration_views_render(): void
    {
        foreach (['/crm', '/crm/requests', '/sites', '/sites/create', '/sites/'.$this->site->id.'/edit', '/users', '/users/create'] as $path) {
            $this->signIn($this->admin)->get($path)->assertOk();
        }
    }

    public function test_guest_cannot_punch(): void
    {
        $this->postJson('/staff/punch', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_disabled_staff_cannot_punch(): void
    {
        $this->staff->update(['active' => false]);
        $this->punch()->assertUnauthorized();
    }

    public function test_password_stamp_change_revokes_existing_session(): void
    {
        $this->actingAs($this->staff)->withSession(['password_stamp' => 'old-stamp'])
            ->postJson('/staff/punch', $this->payload())->assertUnauthorized();
    }

    public function test_valid_punch_stores_authenticated_identity_and_utc_time(): void
    {
        $this->punch(['user_id' => $this->admin->id, 'work_site_id' => 999, 'punched_at' => '2001-01-01'])
            ->assertOk()->assertJson(['ok' => true, 'replay' => false, 'action' => 'in']);
        $session = AttendanceSession::firstOrFail();
        $this->assertSame($this->staff->id, $session->user_id);
        $this->assertSame($this->site->id, $session->work_site_id);
        $this->assertSame('2026-10-06 12:00:00', $session->getRawOriginal('started_at'));
        $this->assertSame('2026-10-06', $session->work_date);
        $this->assertDatabaseCount('attendance_punches', 1);
    }

    public function test_location_is_required(): void
    {
        $data = $this->payload(); unset($data['latitude']);
        $this->signIn($this->staff)->postJson('/staff/punch', $data)->assertUnprocessable()->assertJsonValidationErrors('latitude');
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $this->punch(['latitude' => 91])->assertUnprocessable()->assertJsonValidationErrors('latitude');
    }

    public function test_inaccurate_punch_in_is_not_saved(): void
    {
        $this->punch(['accuracy_m' => 101])->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_outside_geofence_punch_in_is_not_saved(): void
    {
        $this->punch(['latitude' => 10.01])->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->assertDatabaseCount('attendance_punches', 0);
    }

    public function test_record_only_mode_accepts_a_location_outside_radius(): void
    {
        $this->site->update(['enforce_geofence' => false]);
        $this->punch(['latitude' => 10.01])->assertOk();
    }

    public function test_attendance_disabled_prevents_starting_shift(): void
    {
        $this->staff->update(['attendance_enabled' => false]);
        $this->punch()->assertUnprocessable()->assertJsonValidationErrors('attendance');
    }

    public function test_unassigned_staff_cannot_start_shift(): void
    {
        $this->staff->update(['work_site_id' => null]);
        $this->punch()->assertUnprocessable()->assertJsonValidationErrors('attendance');
    }

    public function test_inactive_site_prevents_starting_shift(): void
    {
        $this->site->update(['active' => false]);
        $this->punch()->assertUnprocessable()->assertJsonValidationErrors('attendance');
    }

    public function test_same_request_is_idempotent(): void
    {
        $data = $this->payload();
        $this->signIn($this->staff)->postJson('/staff/punch', $data)->assertOk();
        $this->signIn($this->staff)->postJson('/staff/punch', $data)->assertOk()->assertJson(['replay' => true]);
        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertDatabaseCount('attendance_punches', 1);
    }

    public function test_reusing_uuid_with_changed_payload_conflicts(): void
    {
        $data = $this->payload();
        $this->signIn($this->staff)->postJson('/staff/punch', $data)->assertOk();
        $data['note'] = 'Changed note after an acknowledged punch';
        $this->signIn($this->staff)->postJson('/staff/punch', $data)->assertConflict();
    }

    public function test_second_request_cannot_create_a_second_open_shift(): void
    {
        $this->openShift();
        $this->punch()->assertUnprocessable()->assertJsonValidationErrors('attendance');
        $this->assertDatabaseCount('attendance_sessions', 1);
    }

    public function test_punch_out_records_duration_and_closes_session(): void
    {
        $session = $this->openShift(); $this->advance(2);
        $this->punch(['action' => 'out', 'session_id' => $session->id])->assertOk()->assertJson(['needs_review' => false]);
        $this->assertNull($session->fresh()->open_user_id);
        $this->assertSame(7200, $session->fresh()->closedSeconds());
        $this->assertDatabaseCount('attendance_punches', 2);
    }

    public function test_punch_out_requires_explicit_session(): void
    {
        $this->openShift();
        $this->punch(['action' => 'out'])->assertUnprocessable()->assertJsonValidationErrors('session_id');
    }

    public function test_staff_cannot_close_someone_elses_session(): void
    {
        $session = $this->openShift();
        $other = $this->person('staff', 'other@example.test');
        $this->punch(['action' => 'out', 'session_id' => $session->id], $other)->assertNotFound();
        $this->assertNull($session->fresh()->ended_at);
    }

    public function test_outside_or_inaccurate_punch_out_is_saved_for_review(): void
    {
        $session = $this->openShift(); $this->advance();
        $this->punch(['action' => 'out', 'session_id' => $session->id, 'latitude' => 10.01, 'accuracy_m' => 150])
            ->assertOk()->assertJson(['needs_review' => true]);
        $this->assertTrue($session->fresh()->needs_review);
        $this->assertNotNull($session->fresh()->ended_at);
    }

    public function test_session_uses_original_site_rules_even_if_assignment_changes(): void
    {
        $session = $this->openShift(); $this->advance();
        $this->staff->update(['attendance_enabled' => false, 'work_site_id' => null]);
        $this->site->update(['active' => false, 'latitude' => 12, 'max_accuracy_m' => 1]);
        $this->punch(['action' => 'out', 'session_id' => $session->id])->assertOk()->assertJson(['needs_review' => false]);
    }

    public function test_old_out_request_cannot_close_a_new_shift(): void
    {
        $first = $this->openShift(); $this->advance();
        $old = $this->payload(['action' => 'out', 'session_id' => $first->id]);
        $this->signIn($this->staff)->postJson('/staff/punch', $old)->assertOk();
        $this->advance(); $this->punch()->assertOk();
        $this->signIn($this->staff)->postJson('/staff/punch', $old)->assertOk()->assertJson(['replay' => true]);
        $this->assertSame(1, AttendanceSession::whereNotNull('open_user_id')->count());
    }

    public function test_shift_over_24_hours_requires_review(): void
    {
        $session = $this->openShift(); $this->advance(25);
        $this->punch(['action' => 'out', 'session_id' => $session->id])->assertOk()->assertJson(['needs_review' => true]);
    }

    public function test_work_date_uses_site_timezone_not_server_calendar_date(): void
    {
        $now = CarbonImmutable::parse('2026-10-06 20:00:00', 'UTC');
        Carbon::setTestNow($now); CarbonImmutable::setTestNow($now);
        $this->openShift();
        $this->assertSame('2026-10-07', AttendanceSession::firstOrFail()->work_date);
    }

    public function test_own_session_is_visible_but_other_staff_session_is_hidden(): void
    {
        $session = $this->openShift();
        $this->signIn($this->staff)->get('/staff/history/'.$session->id)->assertOk();
        $this->signIn($this->person('staff', 'private@example.test'))->get('/staff/history/'.$session->id)->assertNotFound();
    }

    public function test_staff_correction_is_pending_and_does_not_rewrite_punch(): void
    {
        $session = $this->openShift();
        $this->signIn($this->staff)->post('/staff/requests', [
            'work_date' => $session->work_date, 'attendance_session_id' => $session->id,
            'reason' => 'The device lost location before my punch-out.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', AttendanceCorrection::firstOrFail()->status);
        $this->assertNull($session->fresh()->ended_at);
        $this->assertDatabaseCount('attendance_punches', 1);
    }

    public function test_duplicate_pending_correction_for_same_day_is_rejected(): void
    {
        $this->correction();
        $this->signIn($this->staff)->post('/staff/requests', [
            'work_date' => '2026-10-05', 'reason' => 'Another request for the same missing attendance day.',
        ])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('attendance_corrections', 1);
    }

    public function test_correction_for_another_users_session_is_hidden(): void
    {
        $session = $this->openShift();
        $this->signIn($this->person('staff', 'request@example.test'))->post('/staff/requests', [
            'work_date' => $session->work_date, 'attendance_session_id' => $session->id,
            'reason' => 'Trying to reference another employee attendance.',
        ])->assertNotFound();
    }

    public function test_future_correction_is_not_accepted(): void
    {
        $this->signIn($this->staff)->post('/staff/requests', [
            'work_date' => '2026-10-08', 'reason' => 'Future dates should not be accepted by the server.',
        ])->assertSessionHasErrors('work_date');
    }

    public function test_approved_missing_day_has_effective_times_without_fabricated_gps(): void
    {
        $request = $this->correction();
        $this->review($request)->assertRedirect('/crm/requests')->assertSessionHasNoErrors();
        $session = AttendanceSession::firstOrFail();
        $this->assertSame('manual_admin', $session->source);
        $this->assertSame(8*3600, $session->closedSeconds());
        $this->assertDatabaseCount('attendance_punches', 0);
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_review_changes_effective_times_but_preserves_raw_punches(): void
    {
        $session = $this->openShift(); $raw = AttendancePunch::firstOrFail()->getRawOriginal();
        $this->advance(2);
        $request = $this->correction($session);
        $this->review($request, ['started_at' => '2026-10-06T17:30', 'ended_at' => '2026-10-06T18:30'])
            ->assertSessionHasNoErrors();
        $this->assertSame($raw, AttendancePunch::firstOrFail()->getRawOriginal());
        $this->assertSame(3600, $session->fresh()->closedSeconds());
        $this->assertNotNull($request->fresh()->before_values);
        $this->assertNotNull($request->fresh()->after_values);
    }

    public function test_rejection_leaves_effective_and_raw_attendance_unchanged(): void
    {
        $session = $this->openShift(); $request = $this->correction($session);
        $this->review($request, ['decision' => 'rejected', 'started_at' => null, 'ended_at' => null])
            ->assertSessionHasNoErrors();
        $this->assertNull($session->fresh()->ended_at);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertDatabaseCount('attendance_punches', 1);
    }

    public function test_admin_cannot_review_their_own_request(): void
    {
        $own = AttendanceCorrection::create([
            'user_id' => $this->admin->id, 'work_site_id' => $this->site->id, 'work_date' => '2026-10-05',
            'timezone' => 'Asia/Kolkata', 'reason' => 'Please check a missing device punch.',
        ]);

        foreach (['approved', 'rejected'] as $decision) {
            $this->review($own, ['decision' => $decision])->assertSessionHasErrors('correction');
        }
        $this->assertSame('pending', $own->fresh()->status);
        $this->assertNull($own->fresh()->reviewed_by);
        $this->assertDatabaseCount('attendance_sessions', 0);

        $this->signIn($this->admin)->get('/crm/requests/'.$own->id)->assertOk()
            ->assertSee('This is your own request.')->assertDontSee('Save reviewed decision');
    }

    public function test_a_different_admin_can_approve_an_admins_request(): void
    {
        $own = AttendanceCorrection::create([
            'user_id' => $this->admin->id, 'work_site_id' => $this->site->id, 'work_date' => '2026-10-05',
            'timezone' => 'Asia/Kolkata', 'reason' => 'Please check a missing device punch.',
        ]);
        $other = $this->person('admin', 'second-admin@example.test');

        $this->signIn($other)->get('/crm/requests/'.$own->id)->assertOk()->assertSee('Save reviewed decision');
        $this->signIn($other)->post('/crm/requests/'.$own->id, [
            'decision' => 'approved', 'started_at' => '2026-10-05T09:00', 'ended_at' => '2026-10-05T17:00',
            'review_note' => 'Verified with the supervisor and staff register.',
        ])->assertRedirect('/crm/requests')->assertSessionHasNoErrors();

        $this->assertSame('approved', $own->fresh()->status);
        $this->assertSame($other->id, $own->fresh()->reviewed_by);
        $this->assertDatabaseCount('attendance_sessions', 1);
    }

    public function test_review_cannot_be_applied_twice(): void
    {
        $request = $this->correction();
        $this->review($request)->assertSessionHasNoErrors();
        $this->review($request)->assertSessionHasErrors('correction');
        $this->assertDatabaseCount('attendance_sessions', 1);
    }

    public function test_overlapping_manual_sessions_are_not_approved(): void
    {
        $first = $this->correction(); $this->review($first)->assertSessionHasNoErrors();
        $second = $this->correction();
        $this->review($second, ['started_at' => '2026-10-05T16:00', 'ended_at' => '2026-10-05T18:00'])
            ->assertSessionHasErrors('correction');
        $this->assertDatabaseCount('attendance_sessions', 1);
    }

    public function test_future_effective_time_cannot_be_approved(): void
    {
        $request = $this->correction(null, '2026-10-06');
        $this->review($request, ['ended_at' => '2026-10-06T23:00'])->assertSessionHasErrors('correction');
    }

    public function test_admin_can_view_session_review_and_export(): void
    {
        $session = $this->openShift(); $request = $this->correction($session);
        $this->signIn($this->admin)->get('/crm/attendance/'.$session->id)->assertOk();
        $this->signIn($this->admin)->get('/crm/requests/'.$request->id)->assertOk();
        $this->signIn($this->admin)->get('/crm/export?from=2026-10-06&to=2026-10-06')
            ->assertOk()->assertDownload('attendance-2026-10-06-to-2026-10-06.csv');
    }

    public function test_permissions_policy_allows_only_same_origin_geolocation(): void
    {
        $this->signIn($this->staff)->get('/staff')->assertOk()
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
    }
}
