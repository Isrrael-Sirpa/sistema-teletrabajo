<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Requests\RevokeTemporaryDelegationRequest;
use App\Http\Requests\StoreTemporaryDelegationRequest;
use App\Models\OrganizationalUnit;
use App\Models\TemporaryDelegation;
use App\Models\User;
use App\Services\TemporaryDelegationService;
use App\Support\CareerHeadDelegationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemporaryDelegationController extends Controller
{
    public function __construct(private readonly TemporaryDelegationService $delegations) {}

    public function index(Request $request): View
    {
        $this->authorizeModule($request->user());
        $query = TemporaryDelegation::query()->with([
            'organizationalUnit', 'delegator', 'delegate', 'authorizer', 'revoker', 'permissions',
        ]);

        if (! $request->user()->hasInstitutionalGlobalScope()) {
            $query->where('organizational_unit_id', $request->user()->organizational_unit_id)
                ->where('delegator_user_id', $request->user()->getKey());
        }

        $items = $query->orderByDesc('starts_at')->paginate(20);

        return view('delegations.index', compact('items'));
    }

    public function create(Request $request): View
    {
        $this->authorizeModule($request->user());
        $actor = $request->user();
        $careers = OrganizationalUnit::query()
            ->careers()->active()
            ->when(! $actor->hasInstitutionalGlobalScope(), fn (Builder $query) => $query->whereKey($actor->organizational_unit_id))
            ->orderBy('name')->get();
        $heads = User::query()->with('organizationalUnit')->where('status', UserStatus::ACTIVE->value)
            ->role(RoleName::CAREER_HEAD->value)
            ->when(! $actor->hasInstitutionalGlobalScope(), fn (Builder $query) => $query->whereKey($actor->getKey()))
            ->orderBy('name')->get();
        $secretaries = User::query()->with('organizationalUnit')->where('status', UserStatus::ACTIVE->value)
            ->role(RoleName::SECRETARY->value)
            ->when(! $actor->hasInstitutionalGlobalScope(), fn (Builder $query) => $query->where('organizational_unit_id', $actor->organizational_unit_id))
            ->orderBy('name')->get();

        return view('delegations.create', [
            'careers' => $careers,
            'heads' => $heads,
            'secretaries' => $secretaries,
            'capabilities' => CareerHeadDelegationCatalog::permissions(),
            'fixedHead' => $actor->hasRole(RoleName::CAREER_HEAD->value) ? $actor : null,
        ]);
    }

    public function store(StoreTemporaryDelegationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! $request->user()->hasInstitutionalGlobalScope()) {
            $data['organizational_unit_id'] = $request->user()->organizational_unit_id;
            $data['delegator_user_id'] = $request->user()->getKey();
        }

        $this->delegations->create($request->user(), $data);

        return redirect()->route('delegations.index')->with('status', 'La delegación temporal fue registrada correctamente.');
    }

    public function destroy(
        RevokeTemporaryDelegationRequest $request,
        TemporaryDelegation $temporaryDelegation,
    ): RedirectResponse {
        $this->delegations->revoke(
            $request->user(),
            $temporaryDelegation,
            $request->string('revocation_reason')->toString(),
        );

        return back()->with('status', 'La delegación fue revocada inmediatamente.');
    }

    private function authorizeModule(User $actor): void
    {
        abort_unless($actor->can(PermissionName::DELEGATIONS_MANAGE_GLOBAL->value)
            || $actor->can(PermissionName::DELEGATIONS_MANAGE_UNIT->value), 403);
    }
}
