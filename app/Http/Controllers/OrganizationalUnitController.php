<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationalUnitType;
use App\Http\Requests\StoreCareerRequest;
use App\Http\Requests\UpdateCareerRequest;
use App\Models\OrganizationalUnit;
use App\Services\OrganizationalUnitService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrganizationalUnitController extends Controller
{
    public function __construct(private readonly OrganizationalUnitService $units) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', OrganizationalUnit::class);

        $filters = $request->validate([
            'type' => ['nullable', 'in:'.implode(',', array_map(fn (OrganizationalUnitType $type) => $type->value, OrganizationalUnitType::cases()))],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        // La Facultad es la raíz técnica de la jerarquía, no una dependencia operativa.
        $query = OrganizationalUnit::query()->with('parent')
            ->where('type', '!=', OrganizationalUnitType::FACULTY->value);

        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }
        if (($filters['status'] ?? null) === 'active') {
            $query->active();
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        $units = $query
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('organizational-units.index', compact('units'));
    }

    public function show(OrganizationalUnit $unit): View
    {
        Gate::authorize('view', $unit);

        $unit->load('parent');

        return view('organizational-units.show', compact('unit'));
    }

    public function create(): View
    {
        Gate::authorize('create', OrganizationalUnit::class);

        $parents = OrganizationalUnit::query()->active()->orderBy('name')->get();

        return view('organizational-units.create', compact('parents'));
    }

    public function store(StoreCareerRequest $request): RedirectResponse
    {
        try {
            $unit = $this->units->createCareer($request->user(), $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'El nombre o la sigla ya pertenecen a otra unidad.',
            ]);
        }

        return redirect()->route('units.show', $unit)
            ->with('status', 'La unidad fue creada correctamente.');
    }

    public function edit(OrganizationalUnit $unit): View
    {
        Gate::authorize('update', $unit);

        $unit->load('parent');
        $parents = OrganizationalUnit::query()->active()->whereKeyNot($unit->id)->orderBy('name')->get();

        return view('organizational-units.edit', compact('unit', 'parents'));
    }

    public function update(UpdateCareerRequest $request, OrganizationalUnit $unit): RedirectResponse
    {
        try {
            $this->units->updateCareer($request->user(), $unit, $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'El nombre o la sigla ya pertenecen a otra unidad.',
            ]);
        }

        return redirect()->route('units.show', $unit)
            ->with('status', 'La unidad fue actualizada correctamente.');
    }

    public function activate(Request $request, OrganizationalUnit $unit): RedirectResponse
    {
        Gate::authorize('activate', $unit);
        $this->units->setCareerActive($request->user(), $unit, true);

        return back()->with('status', 'La unidad fue activada correctamente.');
    }

    public function deactivate(Request $request, OrganizationalUnit $unit): RedirectResponse
    {
        Gate::authorize('deactivate', $unit);
        $this->units->setCareerActive($request->user(), $unit, false);

        return back()->with('status', 'La unidad fue desactivada. Su historial permanece disponible.');
    }

}
