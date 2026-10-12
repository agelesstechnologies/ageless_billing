<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::create('work_sites', function (Blueprint $t) {
   $t->id(); $t->string('name',100); $t->string('address',500)->nullable();
   $t->string('timezone',64)->default('Asia/Kolkata');
   $t->decimal('latitude',10,7)->nullable(); $t->decimal('longitude',11,7)->nullable();
   $t->unsignedInteger('radius_m')->default(200); $t->unsignedInteger('max_accuracy_m')->default(100);
   $t->boolean('enforce_geofence')->default(false); $t->boolean('active')->default(true); $t->timestamps();
  });
  // No staff assignment or location is guessed during an upgrade.
  DB::table('work_sites')->insert(['name'=>'Main park','timezone'=>'Asia/Kolkata','radius_m'=>200,'max_accuracy_m'=>100,'enforce_geofence'=>false,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  Schema::table('users', function (Blueprint $t) {
   $t->string('employee_code',32)->nullable()->unique(); $t->string('phone',24)->nullable();
   $t->string('department',80)->nullable(); $t->string('designation',80)->nullable();
   $t->foreignId('work_site_id')->nullable()->constrained()->restrictOnDelete();
   $t->boolean('attendance_enabled')->default(false);
  });
  Schema::create('attendance_sessions', function (Blueprint $t) {
   $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
   $t->foreignId('work_site_id')->constrained()->restrictOnDelete();
   // NULL after closure. A database unique constraint backs up the user-row lock.
   $t->unsignedBigInteger('open_user_id')->nullable()->unique();
   $t->date('work_date'); $t->string('timezone',64); $t->string('site_name',100); $t->json('site_snapshot');
   // All attendance business timestamps use UTC DATETIME, independent of DB session time zones.
   $t->dateTime('started_at'); $t->dateTime('ended_at')->nullable();
   $t->string('source',24)->default('browser'); $t->boolean('needs_review')->default(false);
   $t->string('review_note',500)->nullable(); $t->timestamps();
   $t->index(['user_id','work_date']); $t->index(['work_site_id','work_date']);
  });
  Schema::create('attendance_punches', function (Blueprint $t) {
   $t->id(); $t->foreignId('attendance_session_id')->constrained()->restrictOnDelete();
   $t->foreignId('user_id')->constrained()->restrictOnDelete();
   $t->uuid('request_id')->unique(); $t->char('fingerprint',64); $t->string('type',8);
   $t->dateTime('punched_at'); $t->decimal('latitude',10,7); $t->decimal('longitude',11,7);
   $t->decimal('accuracy_m',10,2); $t->unsignedInteger('distance_m')->nullable();
   $t->string('location_status',32); $t->string('ip_address',45)->nullable();
   $t->string('user_agent',255)->nullable(); $t->string('note',500)->nullable();
   $t->unique(['attendance_session_id','type']);
  });
  Schema::create('attendance_corrections', function (Blueprint $t) {
   $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
   $t->foreignId('attendance_session_id')->nullable()->constrained()->restrictOnDelete();
   $t->foreignId('work_site_id')->constrained()->restrictOnDelete();
   $t->date('work_date'); $t->string('timezone',64); $t->text('reason');
   $t->string('status',16)->default('pending'); $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
   $t->text('review_note')->nullable(); $t->json('before_values')->nullable(); $t->json('after_values')->nullable();
   $t->dateTime('reviewed_at')->nullable(); $t->timestamps(); $t->index(['status','work_date']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('attendance_corrections'); Schema::dropIfExists('attendance_punches'); Schema::dropIfExists('attendance_sessions');
  Schema::table('users', function (Blueprint $t) { $t->dropConstrainedForeignId('work_site_id'); $t->dropColumn(['employee_code','phone','department','designation','attendance_enabled']); });
  Schema::dropIfExists('work_sites');
 }
};
