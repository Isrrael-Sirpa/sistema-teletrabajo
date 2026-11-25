<?php

namespace App\Http\Controllers;

use App\Enums\AcademicTitle;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(RoleName::class)],
            'unit' => ['nullable', 'integer', Rule::exists(OrganizationalUnit::class, 'id')],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
        ]);

        $actor = $request->user();
        $query = User::query()->with(['organizationalUnit', 'roles']);

        if (! $actor->hasInstitutionalGlobalScope()) {
            $query->where('organizational_unit_id', $actor->organizational_unit_id);
        }

        if (filled($filters['search'] ?? null)) {
            $search = '%'.Str::lower(Str::squish($filters['search'])).'%';
            $query->where(function ($query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
            });
        }

        if (filled($filters['role'] ?? null)) {
            $query->role($filters['role']);
        }

        if ($actor->hasInstitutionalGlobalScope() && filled($filters['unit'] ?? null)) {
            $query->where('organizational_unit_id', $filters['unit']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();
        $roles = array_values(array_filter(RoleName::cases(), fn (RoleName $role): bool => $role->isAssignable()));
        $statuses = UserStatus::cases();
        $units = OrganizationalUnit::query()->availableAsDocumentDestination()->orderBy('name')->get();

        return view('users.index', compact('users', 'roles', 'statuses', 'units'));
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);
        $user->load(['organizationalUnit', 'roles']);

        return view('users.show', compact('user'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', $this->formData($request->user()));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        try {
            $user = $this->users->create($request->user(), $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe un usuario con este correo electrónico.',
            ]);
        }

        return redirect()->route('users.show', $user)
            ->with('status', 'El usuario fue creado correctamente.');
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);
        $user->load(['organizationalUnit', 'roles']);

        return view('users.edit', [
            ...$this->formData($request->user()),
            'user' => $user,
            'isSelf' => $request->user()->is($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        try {
            $this->users->update($request->user(), $user, $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe otro usuario con este correo electrónico.',
            ]);
        }

        return redirect()->route('users.show', $user)
            ->with('status', 'Los datos del usuario fueron actualizados correctamente.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('activate', $user);
        $this->users->setActive($request->user(), $user, true);

        return back()->with('status', 'El usuario fue activado correctamente.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);
        $this->users->setActive($request->user(), $user, false);

        return back()->with('status', 'El usuario fue desactivado sin eliminar su información.');
    }

    public function editPassword(User $user): View
    {
        Gate::authorize('resetPassword', $user);

        return view('users.password', compact('user'));
    }

    public function updatePassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->users->resetPassword($request->user(), $user, $request->string('password')->toString());

        return redirect()->route('users.show', $user)
            ->with('status', 'La contraseña fue restablecida correctamente.');
    }

    /**
     * @return array{roles: array<int, RoleName>, statuses: array<int, UserStatus>, units: mixed}
     */
    private function formData(User $actor): array
    {
        $units = OrganizationalUnit::query()
            ->availableAsDocumentDestination()
            ->when(
                ! $actor->hasInstitutionalGlobalScope(),
                fn ($query) => $query->whereKey($actor->organizational_unit_id),
            )
            ->orderBy('name')
            ->get();

        return [
            'roles' => $this->users->allowedRolesFor($actor),
            'statuses' => UserStatus::cases(),
            'academicTitles' => AcademicTitle::cases(),
            'units' => $units,
        ];
    }
}
