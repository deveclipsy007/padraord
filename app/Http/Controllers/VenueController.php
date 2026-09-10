<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Venue;
use App\Rules\TaxId;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VenueController extends Controller
{
    public const TYPES = [
        'hotel', 'centro_convencoes', 'casa_eventos', 'teatro', 'galpao',
        'area_externa', 'espaco_cliente', 'clube', 'restaurante', 'outro',
    ];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $venues = Venue::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")))
            ->when($request->query('status', 'ativo') !== 'todos', fn ($query) => $query->where('status', $request->query('status', 'ativo')))
            ->withCount('opportunities')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Venues', [
            'venues' => $venues,
            'filters' => ['q' => $search, 'status' => $request->query('status', 'ativo')],
            'types' => self::TYPES,
        ]);
    }

    public function show(Venue $venue)
    {
        return Inertia::render('VenueProfile', [
            'venue' => $venue,
            'types' => self::TYPES,
            'history' => $venue->opportunities()
                ->latest('updated_at')
                ->limit(20)
                ->get(['id', 'title', 'client_name', 'stage', 'event_date'])
                ->all(),
        ]);
    }

    public function store(Request $request)
    {
        $venue = Venue::create($this->data($request));
        $this->audit($request, 'venue.created', $venue);

        return redirect("/venues/{$venue->id}")->with('success', 'Local cadastrado. Complete as medidas depois da visita técnica.');
    }

    public function update(Request $request, Venue $venue)
    {
        $expected = (int) $request->input('revision', $venue->revision);
        if ($expected !== (int) $venue->revision) {
            throw ValidationException::withMessages(['revision' => 'O local foi alterado por outra pessoa. Recarregue antes de salvar.']);
        }

        $venue->update($this->data($request) + ['revision' => $venue->revision + 1]);
        $this->audit($request, 'venue.updated', $venue);

        return back()->with('success', 'Local atualizado.');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'venue_type' => ['sometimes', Rule::in(self::TYPES)],
            'tax_id' => ['nullable', 'string', 'max:20', new TaxId('cnpj')],
            'address' => ['nullable', 'array'],
            'address.cep' => ['nullable', 'string', 'max:12'],
            'address.logradouro' => ['nullable', 'string', 'max:200'],
            'address.numero' => ['nullable', 'string', 'max:20'],
            'address.bairro' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],

            'capacity_seated' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'capacity_standing' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'capacity_cocktail' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'capacity_auditorium' => ['nullable', 'integer', 'min:0', 'max:200000'],

            'floor_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'ceiling_height_m' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'column_notes' => ['nullable', 'string', 'max:2000'],
            'floor_load_kg_m2' => ['nullable', 'numeric', 'min:0', 'max:100000'],

            'door_width_m' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'door_height_m' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'has_loading_dock' => ['sometimes', 'boolean'],
            'has_freight_elevator' => ['sometimes', 'boolean'],
            'elevator_capacity_kg' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'load_in_notes' => ['nullable', 'string', 'max:2000'],

            'power_available_kva' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'power_phases' => ['nullable', 'string', 'max:40'],
            'has_generator_area' => ['sometimes', 'boolean'],
            'power_notes' => ['nullable', 'string', 'max:2000'],

            'noise_curfew_time' => ['nullable', 'date_format:H:i'],
            'load_in_window' => ['nullable', 'string', 'max:120'],
            'load_out_window' => ['nullable', 'string', 'max:120'],
            'parking_spots' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'has_kitchen' => ['sometimes', 'boolean'],
            'catering_policy' => ['sometimes', Rule::in(['livre', 'exclusivo', 'lista_aprovada'])],

            'restrictions' => ['nullable', 'string', 'max:5000'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(['ativo', 'inativo'])],
        ]);

        if (array_key_exists('tax_id', $data)) {
            $data['tax_id'] = blank($data['tax_id']) ? null : TaxId::digits($data['tax_id']);
        }
        if (! empty($data['state'])) {
            $data['state'] = mb_strtoupper($data['state']);
        }

        return $data;
    }

    private function audit(Request $request, string $action, Venue $venue): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => Venue::class,
            'subject_id' => $venue->id,
            'metadata' => ['name' => $venue->name, 'revision' => $venue->revision],
        ]);
    }
}
