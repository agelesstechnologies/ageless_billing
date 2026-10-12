<?php
namespace App\Http\Controllers;

use App\Domain\AttendancePolicy;
use App\Models\{AttendanceSession, AttendanceCorrection, WorkSite, User};
use App\Services\{AttendanceCorrectionService, Audit};
use App\Support\Csv;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceAdminController {
    public function index(Request $r) {
        $filters = $this->filters($r);
        $query = $this->query($filters);

        $rows = $query->with(['user', 'punches'])->orderByDesc('started_at')->paginate(25)->withQueryString();

        $active = AttendanceSession::whereNotNull('open_user_id')
            ->when($filters['site_id'] ?? null, fn($q, $id) => $q->where('work_site_id', $id))
            ->count();

        $people = (clone $query)->distinct()->count('user_id');
        $review = (clone $query)->where('needs_review', true)->count();

        $seconds = 0;
        foreach ((clone $query)->whereNotNull('ended_at')->where('needs_review', false)->cursor() as $s) {
            $seconds += $s->closedSeconds();
        }

        $pending = AttendanceCorrection::where('status', 'pending')->count();

        $trades = User::whereNotNull('trade')->where('trade', '!=', '')->distinct()->pluck('trade')
            ->merge(User::whereNotNull('designation')->where('designation', '!=', '')->distinct()->pluck('designation'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $sites = WorkSite::orderBy('name')->get();
        $staff = User::orderBy('name')->get();

        return view('crm.attendance', compact('rows', 'filters', 'active', 'people', 'review', 'seconds', 'pending', 'trades', 'sites', 'staff'));
    }

    public function show(AttendanceSession $attendance) {
        $attendance->load('user', 'punches', 'corrections.reviewer');
        return view('crm.detail', compact('attendance'));
    }

    public function export(Request $r) {
        $filters = $this->filters($r);
        $query = $this->query($filters)->with(['user', 'punches'])->orderBy('started_at');
        Audit::record('attendance.exported', 'attendance_session', null, $filters);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            Csv::row($out, [
                'Session',
                'Employee ID',
                'Name',
                'Mobile number',
                'Trade / job role',
                'Department',
                'Site',
                'Work date (punch-in date)',
                'Timezone',
                'Effective in',
                'Effective out',
                'Recorded minutes',
                'Review required',
                'Source',
                'Raw in UTC',
                'In latitude',
                'In longitude',
                'In accuracy m',
                'In distance m',
                'In location status',
                'Raw out UTC',
                'Out latitude',
                'Out longitude',
                'Out accuracy m',
                'Out distance m',
                'Out location status',
            ]);

            foreach ($query->lazy(200) as $s) {
                $in = $s->punches->firstWhere('type', 'in');
                $outPunch = $s->punches->firstWhere('type', 'out');
                Csv::row($out, [
                    $s->id,
                    $s->user->employee_code,
                    $s->user->name,
                    $s->user->phone,
                    $s->user->jobRole(),
                    $s->user->department,
                    $s->site_name,
                    $s->work_date,
                    $s->timezone,
                    $s->localTime('started_at', 'Y-m-d H:i:s'),
                    $s->localTime('ended_at', 'Y-m-d H:i:s'),
                    $s->ended_at ? intdiv($s->closedSeconds(), 60) : '',
                    $s->needs_review ? 'Yes' : 'No',
                    $s->source,
                    $in?->punched_at?->toIso8601String(),
                    $in?->latitude,
                    $in?->longitude,
                    $in?->accuracy_m,
                    $in?->distance_m,
                    $in?->location_status,
                    $outPunch?->punched_at?->toIso8601String(),
                    $outPunch?->latitude,
                    $outPunch?->longitude,
                    $outPunch?->accuracy_m,
                    $outPunch?->distance_m,
                    $outPunch?->location_status,
                ]);
            }
            fclose($out);
        }, 'attendance-' . $filters['from'] . '-to-' . $filters['to'] . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function requests(Request $r) {
        $data = $r->validate(['status' => 'nullable|in:pending,approved,rejected']);
        $status = $data['status'] ?? 'pending';
        $requests = AttendanceCorrection::where('status', $status)->with('user', 'workSite', 'session')->latest()->paginate(20)->withQueryString();
        return view('crm.requests', compact('requests', 'status'));
    }

    public function reviewForm(AttendanceCorrection $correction) {
        $correction->load('user', 'session', 'workSite', 'reviewer');
        return view('crm.review', compact('correction'));
    }

    public function review(Request $r, AttendanceCorrection $correction, AttendanceCorrectionService $service) {
        $data = $r->validate([
            'decision' => 'required|in:approved,rejected',
            'review_note' => 'required|string|min:10|max:1500',
            'started_at' => 'nullable|required_if:decision,approved|date_format:Y-m-d\TH:i',
            'ended_at' => 'nullable|required_if:decision,approved|date_format:Y-m-d\TH:i',
        ]);
        $service->review($correction, $r->user(), $data);
        return redirect()->route('crm.requests')->with('success', 'Request ' . $data['decision'] . '. Original device punches were preserved.');
    }

    public function createReview(Request $r, AttendanceSession $attendance) {
        $data = $r->validate(['reason' => 'required|string|min:10|max:1500']);
        $record = \Illuminate\Support\Facades\DB::transaction(function () use ($attendance, $data) {
            User::whereKey($attendance->user_id)->lockForUpdate()->firstOrFail();
            $existing = AttendanceCorrection::where('user_id', $attendance->user_id)->where('work_date', $attendance->work_date)->where('status', 'pending')->first();
            if ($existing) {
                return $existing;
            }
            $record = AttendanceCorrection::create([
                'user_id' => $attendance->user_id,
                'attendance_session_id' => $attendance->id,
                'work_site_id' => $attendance->work_site_id,
                'timezone' => $attendance->timezone,
                'work_date' => $attendance->work_date,
                'reason' => 'Administrator review: ' . $data['reason'],
            ]);
            Audit::record('attendance.admin_review_requested', 'attendance_correction', $record->id);
            return $record;
        });
        return redirect()->route('crm.review', $record);
    }

    private function filters(Request $r): array {
        $d = $r->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'site_id' => 'nullable|integer|exists:work_sites,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'trade' => 'nullable|string|max:100',
            'status' => 'nullable|in:all,open,closed,review',
            'search' => 'nullable|string|max:100',
        ]);

        $d['from'] = $d['from'] ?? now()->toDateString();
        $d['to'] = $d['to'] ?? $d['from'];
        $d['status'] = $d['status'] ?? 'all';
        $d['search'] = trim((string)($d['search'] ?? ''));
        $d['trade'] = trim((string)($d['trade'] ?? ''));

        if ($d['to'] < $d['from'] || CarbonImmutable::parse($d['from'])->diffInDays(CarbonImmutable::parse($d['to'])) > 366) {
            throw ValidationException::withMessages(['from' => 'Choose a date range of up to 366 days, with the end on or after the start.']);
        }

        return $d;
    }

    private function query(array $f) {
        return AttendanceSession::whereBetween('work_date', [$f['from'], $f['to']])
            ->when($f['site_id'] ?? null, fn($q, $id) => $q->where('work_site_id', $id))
            ->when($f['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
            ->when(!empty($f['trade']), function ($q) use ($f) {
                $trade = $f['trade'];
                $q->whereHas('user', fn($qu) => $qu->where('trade', $trade)->orWhere('designation', $trade));
            })
            ->when(!empty($f['search']), function ($q) use ($f) {
                $search = '%' . $f['search'] . '%';
                $q->whereHas('user', function ($qu) use ($search) {
                    $qu->where(function ($w) use ($search) {
                        $w->where('name', 'like', $search)
                          ->orWhere('phone', 'like', $search)
                          ->orWhere('email', 'like', $search)
                          ->orWhere('employee_code', 'like', $search);
                    });
                });
            })
            ->when($f['status'] === 'open', fn($q) => $q->whereNull('ended_at'))
            ->when($f['status'] === 'closed', fn($q) => $q->whereNotNull('ended_at'))
            ->when($f['status'] === 'review', fn($q) => $q->where('needs_review', true));
    }
}
