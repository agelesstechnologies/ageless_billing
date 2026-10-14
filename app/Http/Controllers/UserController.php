<?php
namespace App\Http\Controllers;

use App\Models\{User, WorkSite};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Rules\StrongPassword;
use Illuminate\Validation\{Rule, ValidationException};

class UserController {
    public const COMMON_TRADES = [
        'Ride Operator',
        'Technician',
        'Electrician',
        'Housekeeping',
        'Security',
        'Ticketing & Cashier',
        'Maintenance',
        'Guest Services',
        'Safety & First Aid',
    ];

    public function index(Request $r) {
        $search = trim((string)$r->input('search', ''));
        $status = $r->input('status', 'all');
        $trade = trim((string)$r->input('trade', ''));
        $role = $r->input('role', 'all');

        $query = User::with('workSite');

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('phone', 'like', $term)
                  ->orWhere('trade', 'like', $term)
                  ->orWhere('designation', 'like', $term)
                  ->orWhere('employee_code', 'like', $term);
            });
        }

        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        if ($trade !== '') {
            $query->where(function ($q) use ($trade) {
                $q->where('trade', $trade)->orWhere('designation', $trade);
            });
        }

        if ($role !== 'all' && in_array($role, ['admin', 'cashier', 'staff'], true)) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('name')->get();

        $allTrades = User::whereNotNull('trade')->where('trade', '!=', '')->distinct()->pluck('trade')
            ->merge(User::whereNotNull('designation')->where('designation', '!=', '')->distinct()->pluck('designation'))
            ->merge(self::COMMON_TRADES)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $totalCount = User::count();
        $activeCount = User::where('active', true)->count();
        $inactiveCount = User::where('active', false)->count();

        return view('users.index', compact('users', 'search', 'status', 'trade', 'role', 'allTrades', 'totalCount', 'activeCount', 'inactiveCount'));
    }

    public function create() {
        $defaultSite = WorkSite::where('active', true)->first();
        $staff = new User([
            'active' => true,
            'role' => 'staff',
            'attendance_enabled' => true,
            'work_site_id' => $defaultSite?->id,
        ]);
        $sites = WorkSite::orderBy('name')->get();
        $trades = self::COMMON_TRADES;

        return view('users.form', compact('staff', 'sites', 'trades'));
    }

    public function edit(User $user) {
        $sites = WorkSite::orderBy('name')->get();
        $trades = self::COMMON_TRADES;

        return view('users.form', ['staff' => $user, 'sites' => $sites, 'trades' => $trades]);
    }

    private function data(Request $r, ?User $u = null): array {
        $r->merge(['email' => strtolower(trim((string)$r->input('email')))]);

        $d = $r->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($u?->id)],
            'password' => [$u ? 'nullable' : 'required', 'confirmed', new StrongPassword],
            'role' => 'required|in:admin,cashier,staff',
            'active' => 'nullable|boolean',
            'employee_code' => ['nullable', 'string', 'max:32', Rule::unique('users')->ignore($u?->id)],
            'phone' => 'nullable|string|max:24',
            'trade' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:80',
            'designation' => 'nullable|string|max:80',
            'work_site_id' => 'nullable|integer|exists:work_sites,id',
            'attendance_enabled' => 'nullable|boolean',
        ]);

        // Keep one login identity across SQLite's case-sensitive uniqueness and
        // MySQL's common case-insensitive collations, including older accounts.
        $sameEmail = User::whereRaw('LOWER(TRIM(email)) = ?', [$d['email']]);
        if ($u) $sameEmail->where('id', '!=', $u->id);
        if ($sameEmail->exists()) {
            throw ValidationException::withMessages(['email' => 'This email is already used by another account.']);
        }

        $d['active'] = $r->boolean('active', true);
        $d['attendance_enabled'] = $r->boolean('attendance_enabled', true);

        // Normalize trade and designation
        if (!empty($d['trade'])) {
            $d['trade'] = trim($d['trade']);
            if (empty($d['designation'])) {
                $d['designation'] = $d['trade'];
            }
        } elseif (!empty($d['designation'])) {
            $d['trade'] = trim($d['designation']);
        }

        // No silent default: the work site is whatever the administrator chose (or none).
        if (array_key_exists('work_site_id', $d)) {
            $d['work_site_id'] = $d['work_site_id'] ?: null;
        }

        if (empty($d['password'])) {
            unset($d['password']);
        }

        return $d;
    }

    public function store(Request $r) {
        $data = $this->data($r);
        $user = DB::transaction(function () use ($data) {
            $created = User::create($data);

            return $created;
        });

        return redirect()->route('users.index')->with('success', "Employee '{$user->name}' created successfully.");
    }

    public function update(Request $r, User $user) {
        $data = $this->data($r, $user);

        DB::transaction(function () use ($data, $user, $r) {
            $admins = User::where('active', true)->where('role', 'admin')->orderBy('id')->lockForUpdate()->get();

            if ($user->id === $r->user()->id && (!$data['active'] || $data['role'] !== 'admin')) {
                throw ValidationException::withMessages(['role' => 'Do not disable or demote your own administrator account.']);
            }

            if ($admins->contains('id', $user->id) && $admins->count() === 1 && (!$data['active'] || $data['role'] !== 'admin')) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active administrator.']);
            }

            $user->update($data);

        });

        return redirect()->route('users.index')->with('success', "Employee '{$user->name}' updated successfully.");
    }

    public function toggleStatus(Request $r, User $user) {
        DB::transaction(function () use ($user, $r) {
            $newStatus = !$user->active;
            $admins = User::where('active', true)->where('role', 'admin')->orderBy('id')->lockForUpdate()->get();

            if ($user->id === $r->user()->id && !$newStatus) {
                throw ValidationException::withMessages(['status' => 'You cannot deactivate your own administrator account.']);
            }

            if ($admins->contains('id', $user->id) && $admins->count() === 1 && !$newStatus) {
                throw ValidationException::withMessages(['status' => 'Cannot deactivate the last active administrator.']);
            }

            $user->update(['active' => $newStatus]);

        });

        $msg = $user->active ? "Account for '{$user->name}' is now Active." : "Account for '{$user->name}' has been Deactivated.";
        return redirect()->route('users.index')->with('success', $msg);
    }
}
