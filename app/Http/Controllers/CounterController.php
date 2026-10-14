<?php
namespace App\Http\Controllers;

use App\Models\{Sale, User, WorkSite};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Rules\StrongPassword;
use Illuminate\Validation\{Rule, ValidationException};

class CounterController {
    public function index() {
        $counters = User::where('role', 'cashier')->orderBy('name')->get();
        $today = now()->startOfDay();
        $stats = Sale::where('status', 'paid')->whereIn('user_id', $counters->pluck('id'))
            ->selectRaw('user_id, COUNT(*) as bills, SUM(total_paise) as total, SUM(CASE WHEN created_at >= ? THEN total_paise ELSE 0 END) as today_total', [$today])
            ->groupBy('user_id')->get()->keyBy('user_id');

        return view('counters.index', compact('counters', 'stats'));
    }

    public function create() {
        return view('counters.form');
    }

    public function store(Request $r) {
        $r->merge(['employee_code' => trim((string)$r->input('employee_code')), 'email' => strtolower(trim((string)$r->input('email')))]);
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'employee_code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._@+-]+$/', Rule::unique('users', 'employee_code')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => 'nullable|string|max:24',
            'password' => ['required', 'confirmed', new StrongPassword],
        ], [], ['employee_code' => 'login ID']);

        if (User::whereRaw('LOWER(TRIM(employee_code)) = ?', [strtolower($d['employee_code'])])->exists()) {
            throw ValidationException::withMessages(['employee_code' => 'This login ID is already used.']);
        }

        // Login is by Counter ID; users.email is required/unique, so synthesise one when none is given.
        $email = $d['email'] ?: strtolower($d['employee_code']) . '@counter.local';
        if (User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'This email is already used by another account.']);
        }

        $counter = DB::transaction(function () use ($d, $email) {
            $user = User::create([
                'name' => $d['name'],
                'email' => $email,
                'password' => $d['password'],
                'role' => 'cashier',
                'active' => true,
                'employee_code' => $d['employee_code'],
                'phone' => $d['phone'] ?? null,
                'trade' => 'Ticketing & Cashier',
                'designation' => 'Ticketing & Cashier',
                'attendance_enabled' => false,
                'work_site_id' => WorkSite::where('active', true)->first()?->id,
            ]);

            return $user;
        });

        return redirect()->route('counters.index')->with('success', "Ticket counter '{$counter->name}' created. Login ID: {$counter->employee_code}");
    }
}
