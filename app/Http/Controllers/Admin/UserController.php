<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * GET /api/admin/users
     * List users (admin only) with pagination and search.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));

        $query = User::query()
            ->where('role', User::ROLE_USER)
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->through(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'national_id' => $user->national_id,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'status' => $user->status,
                    'role' => $user->role,
                    'created_at' => optional($user->created_at)->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * POST /api/portallogistice/admin/users
     * Create a new user (by admin).
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name', '')),
            'national_id' => trim((string) $request->input('national_id', '')),
            // Treat empty optional fields as null to avoid false unique conflicts on empty strings.
            'phone' => ($request->has('phone') && trim((string) $request->input('phone')) !== '')
                ? trim((string) $request->input('phone'))
                : null,
            'email' => ($request->has('email') && trim((string) $request->input('email')) !== '')
                ? trim((string) $request->input('email'))
                : null,
        ]);

        $validated = $request->validate(
            [
                'name' => 'required|string|max:255',
                'national_id' => 'required|string|max:20|unique:users,national_id',
                'phone' => 'nullable|string|max:20|unique:users,phone',
                'email' => 'nullable|email|unique:users,email',
                'password' => 'required|string|min:6|max:72',
            ],
            [
                'national_id.unique' => 'رقم الهوية مستخدم مسبقًا',
            ]
        );

        $password = (string) $validated['password'];
        $name = trim((string) $validated['name']);

        $user = User::create([
            'first_name' => $name,
            'last_name' => null,
            'name' => $name,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'national_id' => $validated['national_id'],
            'password' => Hash::make($password),
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
            'is_verified' => true,
            'is_first_login' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المستخدم بنجاح',
            'data' => [
                'user' => $user->toApiArray(),
                'login_hint' => 'يمكن تسجيل الدخول باستخدام الهوية الوطنية أو الجوال أو البريد مع كلمة المرور.',
            ],
        ], 201);
    }

    /**
     * POST /api/portallogistice/admin/register
     * Create a new admin (from frontend "إنشاء حساب مدير").
     */
    public function registerAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'first_name' => $validated['name'],
            'last_name' => null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => null,
            'national_id' => null,
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'is_verified' => true,
            'is_first_login' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء حساب المدير بنجاح',
            'data' => [
                'admin' => $user->toApiArray(),
            ],
        ], 201);
    }
      // ── adminShowUser ─────────────────────────────────────────────────────────

    public function adminShowUser(string $identifier): JsonResponse
    {
        $user = $this->findInvestorUser($identifier);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
        }

        $payload              = $user->toApiArray();
        $payload['created_at'] = optional($user->created_at)->toIso8601String();

        return response()->json([
            'success' => true,
            'data'    => ['user' => $payload],
        ]);
    }

    // ── adminUpdateUser ───────────────────────────────────────────────────────

    public function adminUpdateUser(Request $request, string $identifier): JsonResponse
    {
        $user = $this->findInvestorUserForPut($identifier);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
        }

        // Normalise nullable string fields before validation
        foreach (['phone', 'email', 'national_id'] as $field) {
            if ($request->has($field)) {
                $v = trim((string) $request->input($field, ''));
                $request->merge([$field => $v === '' ? null : $v]);
            }
        }

        $validated = $request->validate([
            // identity
            'national_id'      => ['sometimes', 'nullable', 'string', 'max:20',
                                    Rule::unique('users', 'national_id')->ignore($user->id)],
            // name parts
            'name'             => 'sometimes|nullable|string|max:255',
            'first_name'       => 'sometimes|nullable|string|max:255',
            'father_name'      => 'sometimes|nullable|string|max:255',
            'grandfather_name' => 'sometimes|nullable|string|max:255',
            'last_name'        => 'sometimes|nullable|string|max:255',
            'family_name'      => 'sometimes|nullable|string|max:255',
            // contact
            'phone'            => ['sometimes', 'nullable', 'string', 'max:20',
                                    Rule::unique('users', 'phone')->ignore($user->id)],
            'email'            => ['sometimes', 'nullable', 'email',
                                    Rule::unique('users', 'email')->ignore($user->id)],
            // personal
            'birth_date'       => 'sometimes|nullable|date_format:Y-m-d',
            'region'           => 'sometimes|nullable|string|max:255',
            // banking
            'bank_name'        => 'sometimes|nullable|string|max:255',
            'iban'             => 'sometimes|nullable|string|max:34',
            // account
            'password'         => 'sometimes|string|min:6|max:72',
            'status'           => 'sometimes|string|in:active,inactive',
        ], [
            'national_id.unique' => 'رقم الهوية مستخدم مسبقًا',
            'phone.unique'       => 'رقم الجوال مستخدم مسبقًا',
            'email.unique'       => 'البريد الإلكتروني مستخدم مسبقًا',
        ]);

        $updates = [];

        // ── name fields ───────────────────────────────────────────────────────
        if (array_key_exists('first_name', $validated)) {
            $updates['first_name'] = $validated['first_name'];
        }
        if (array_key_exists('father_name', $validated)) {
            $updates['father_name'] = $validated['father_name'];
        }
        if (array_key_exists('grandfather_name', $validated)) {
            $updates['grandfather_name'] = $validated['grandfather_name'];
        }
        // family_name maps to last_name column
        $lastName = $validated['family_name'] ?? $validated['last_name'] ?? null;
        if (array_key_exists('family_name', $validated) || array_key_exists('last_name', $validated)) {
            $updates['last_name'] = $lastName;
        }
        // Rebuild composite name when either part changes
        if (isset($updates['first_name']) || isset($updates['last_name'])) {
            $fn = $updates['first_name'] ?? $user->first_name;
            $ln = array_key_exists('last_name', $updates) ? $updates['last_name'] : $user->last_name;
            $updates['name'] = trim(trim((string) ($fn ?? '')).' '.trim((string) ($ln ?? '')));
        }
        if (array_key_exists('name', $validated) && ! isset($updates['name'])) {
            $n = trim((string) ($validated['name'] ?? ''));
            if ($n !== '') {
                $updates['name']       = $n;
                $updates['first_name'] = $updates['first_name'] ?? $n;
            }
        }

        // ── simple scalar fields ──────────────────────────────────────────────
        foreach (['national_id', 'phone', 'email', 'birth_date', 'region', 'bank_name', 'iban'] as $f) {
            if (array_key_exists($f, $validated)) {
                $updates[$f] = $validated[$f];
            }
        }

        // ── account fields ────────────────────────────────────────────────────
        if (isset($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }
        if (isset($validated['status'])) {
            $updates['status'] = $validated['status'];
            if ($validated['status'] === User::STATUS_INACTIVE) {
                $updates['api_token'] = null;
            }
        }

        if ($updates === []) {
            return response()->json(['success' => false, 'message' => 'لا توجد حقول للتحديث'], 422);
        }

        $user->forceFill($updates)->save();

        $payload               = $user->fresh()->toApiArray();
        $payload['created_at'] = optional($user->created_at)->toIso8601String();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المستخدم',
            'data'    => ['user' => $payload],
        ]);
    }

    // ── activate / deactivate ─────────────────────────────────────────────────

    public function adminActivateUser(int $id): JsonResponse
    {
        return $this->setUserStatus($id, User::STATUS_ACTIVE);
    }

    public function adminDeactivateUser(int $id): JsonResponse
    {
        return $this->setUserStatus($id, User::STATUS_INACTIVE);
    }

    // ── private helpers ───────────────────────────────────────────────────────

    /** Find investor by numeric id only (used by show/activate/deactivate). */
    private function findInvestorUser(string $identifier): ?User
    {
        if (! ctype_digit($identifier)) return null;

        return User::query()
            ->where('role', User::ROLE_USER)
            ->where('id', (int) $identifier)
            ->first();
    }

    /** Find investor for PUT: try national_id first, fall back to numeric id. */
    private function findInvestorUserForPut(string $identifier): ?User
    {
        $key = trim($identifier);
        if ($key === '') return null;

        // Try national_id first (legacy frontend behaviour)
        $byNational = User::query()
            ->where('role', User::ROLE_USER)
            ->where('national_id', $key)
            ->first();
        if ($byNational) return $byNational;

        // Fall back to numeric id
        if (ctype_digit($key)) {
            return User::query()
                ->where('role', User::ROLE_USER)
                ->where('id', (int) $key)
                ->first();
        }

        return null;
    }

    private function setUserStatus(int $id, string $status): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
        }
        if ($user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'لا يمكن تغيير حالة حساب مدير من هنا'], 403);
        }

        $attrs = ['status' => $status];
        if ($status === User::STATUS_INACTIVE) {
            $attrs['api_token'] = null;
        }

        $user->forceFill($attrs)->save();

        return response()->json([
            'success' => true,
            'message' => $status === User::STATUS_ACTIVE ? 'تم تفعيل المستخدم' : 'تم إيقاف المستخدم',
            'data'    => ['user' => $user->fresh()->toApiArray()],
        ]);
    }
}
