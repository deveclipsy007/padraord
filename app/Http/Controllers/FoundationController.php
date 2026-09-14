<?php

namespace App\Http\Controllers;

use App\Enums\Ability;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use App\Rules\TaxId;
use App\Services\ClientContactService;
use App\Services\ClientRegistrationService;
use App\Services\RecordArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FoundationController extends Controller
{
    private function audit(Request $request, string $action, $subject): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id]);
    }

    public function clientStore(Request $request, ClientRegistrationService $service)
    {
        $client = $service->save(null, $request->user(), $this->clientData($request));

        return redirect("/clients/{$client->id}")->with('success', 'Cliente criado.');
    }

    public function clientUpdate(Request $request, Client $client, ClientRegistrationService $service)
    {
        $service->save($client, $request->user(), $this->clientData($request));

        return back()->with('success', 'Cliente atualizado.');
    }

    private function clientData(Request $request): array
    {
        $type = in_array($request->input('tax_id_type'), ['cnpj', 'cpf', 'estrangeiro'], true) ? $request->input('tax_id_type') : 'cnpj';
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'industry' => 'nullable|string|max:160',
            'notes' => 'nullable|string|max:10000',
            'legal_name' => 'nullable|string|max:200',
            'tax_id' => ['nullable', 'string', 'max:20', new TaxId($type)],
            'tax_id_type' => ['sometimes', Rule::in(['cnpj', 'cpf', 'estrangeiro'])],
            'state_registration' => 'nullable|string|max:40',
            'municipal_registration' => 'nullable|string|max:40',
            'billing_email' => 'nullable|email|max:200',
            'billing_address' => 'nullable|array',
            'billing_address.cep' => 'nullable|string|max:12',
            'billing_address.logradouro' => 'nullable|string|max:200',
            'billing_address.numero' => 'nullable|string|max:20',
            'billing_address.complemento' => 'nullable|string|max:120',
            'billing_address.bairro' => 'nullable|string|max:120',
            'billing_address.cidade' => 'nullable|string|max:120',
            'billing_address.uf' => 'nullable|string|size:2',
            'default_payment_terms_days' => 'sometimes|integer|min:0|max:365',
            'segment' => ['sometimes', Rule::in(['corporativo', 'social', 'institucional', 'cultural', 'esportivo', 'religioso', 'governo', 'terceiro_setor'])],
            'tier' => ['sometimes', Rule::in(['prospect', 'ativo', 'recorrente', 'inativo'])],
            'website' => 'nullable|string|max:200',
            'instagram' => 'nullable|string|max:120',
        ]);

        // Guardar só os dígitos: a máscara pertence à interface, e comparar
        // documentos formatados de jeitos diferentes esconde duplicidade.
        if (array_key_exists('tax_id', $data)) {
            $data['tax_id'] = blank($data['tax_id']) ? null : TaxId::digits($data['tax_id']);
        }
        if (isset($data['billing_address']['uf'])) {
            $data['billing_address']['uf'] = mb_strtoupper($data['billing_address']['uf']);
        }

        return $data;
    }

    public function clientShow(Client $client)
    {
        $duplicates = blank($client->tax_id) ? [] : Client::query()
            ->where('tax_id', $client->tax_id)
            ->whereKeyNot($client->id)
            ->get(['id', 'name', 'legal_name'])
            ->all();

        return Inertia::render('ClientProfile', [
            'client' => $client->load(['contacts', 'opportunities']),
            'contractReadiness' => [
                'ready' => $client->readyForContract(),
                'missing' => $client->missingContractData(),
            ],
            'duplicates' => $duplicates,
        ]);
    }

    public function clientArchive(Request $request, Client $client, RecordArchiveService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $service->client($client, $request->user(), $data['reason']);

        return back()->with('success', 'Cliente arquivado; histórico preservado.');
    }

    public function clientRestore(Request $request, Client $client, RecordArchiveService $service)
    {
        $service->restore($client, $request->user());

        return back()->with('success', 'Cliente restaurado.');
    }

    public function contactStore(Request $request, Client $client, ClientContactService $service)
    {
        $service->save($client, null, $request->user(), $this->contactData($request));

        return back()->with('success', 'Contato adicionado.');
    }

    public function contactUpdate(Request $request, Client $client, Contact $contact, ClientContactService $service)
    {
        abort_unless($contact->client_id === $client->id, 404);
        $service->save($client, $contact, $request->user(), $this->contactData($request));

        return back()->with('success', 'Contato atualizado.');
    }

    public function contactArchive(Request $request, Client $client, Contact $contact, RecordArchiveService $service)
    {
        abort_unless($contact->client_id === $client->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $service->contact($contact, $request->user(), $data['reason']);

        return back()->with('success', 'Contato arquivado; histórico preservado.');
    }

    public function contactRestore(Request $request, Client $client, Contact $contact, RecordArchiveService $service)
    {
        abort_unless($contact->client_id === $client->id, 404);
        $service->restore($contact, $request->user());

        return back()->with('success', 'Contato restaurado.');
    }

    private function contactData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:160', 'email' => 'nullable|email|max:160', 'phone' => 'nullable|string|max:60', 'role' => 'nullable|string|max:160',
            'department' => 'nullable|string|max:160', 'whatsapp' => 'nullable|string|max:60',
            'is_primary' => 'sometimes|boolean', 'is_decision_maker' => 'sometimes|boolean',
            'preferred_channel' => ['sometimes', Rule::in(['email', 'phone', 'whatsapp'])], 'revision' => 'sometimes|integer|min:0',
        ]);
    }

    public function userStore(Request $request)
    {
        Gate::authorize(Ability::ManageTeam->value);
        $data = $request->validate(['name' => 'required|string|max:160', 'email' => 'required|email|max:160|unique:users,email', 'password' => 'required|string|min:12|max:128', 'role' => ['required', Rule::in(['admin', 'producer'])]]);
        $user = User::create([...$data, 'is_active' => true, 'can_approve_commercial' => false]);
        $this->audit($request, 'user.created', $user);

        return back()->with('success', 'Usuário criado. Compartilhe a senha por um canal seguro.');
    }

    public function userUpdate(Request $request, User $user)
    {
        Gate::authorize(Ability::ManageTeam->value);
        $data = $request->validate(['name' => 'required|string|max:160', 'email' => ['required', 'email', 'max:160', Rule::unique('users')->ignore($user->id)], 'role' => ['required', Rule::in(['admin', 'producer'])], 'is_active' => 'required|boolean']);
        if ($user->id === $request->user()->id && (! $data['is_active'] || $data['role'] !== 'admin')) {
            return back()->withErrors(['is_active' => 'Não é permitido retirar seu próprio acesso administrativo.']);
        }
        $user->update($data);
        $this->audit($request, 'user.updated', $user);

        return back()->with('success', 'Acesso atualizado. Autoridade comercial permanece separada.');
    }

    public function userPassword(Request $request, User $user)
    {
        Gate::authorize(Ability::ManageTeam->value);
        $data = $request->validate(['password' => 'required|string|min:12|max:128']);
        $user->update([...$data, 'remember_token' => null]);
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $this->audit($request, 'user.password_reset', $user);

        return back()->with('success', 'Senha redefinida. Compartilhe pelo canal seguro da equipe.');
    }

    public function commercialAuthority(Request $request, User $user)
    {
        Gate::authorize(Ability::ManageTeam->value);
        $data = $request->validate([
            'password' => ['required', 'current_password'],
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $before = (bool) $user->can_approve_commercial;
        $enabled = (bool) $data['enabled'];
        $user->update(['can_approve_commercial' => $enabled]);
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.commercial_authority_changed',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'metadata' => ['before' => $before, 'after' => $enabled, 'reason' => $data['reason']],
        ]);

        return back()->with('success', $enabled ? 'Autoridade comercial concedida.' : 'Autoridade comercial removida.');
    }

    public function activityStore(Request $request)
    {
        $activity = Activity::create($this->activityData($request));
        $this->audit($request, 'activity.created', $activity);

        return back()->with('success', 'Atividade criada.');
    }

    public function activityUpdate(Request $request, Activity $activity)
    {
        $activity->update($this->activityData($request));
        $this->audit($request, 'activity.updated', $activity);

        return back()->with('success', 'Atividade atualizada.');
    }

    public function activityComplete(Request $request, Activity $activity)
    {
        $activity->update(['status' => 'done', 'completed_at' => now(), 'is_next_action' => false]);
        if ($activity->opportunity && $activity->opportunity->next_action === $activity->title) {
            $activity->opportunity->update(['next_action' => null, 'next_action_at' => null]);
        }
        $this->audit($request, 'activity.completed', $activity);

        return back()->with('success', 'Atividade concluída.');
    }

    public function activityReopen(Request $request, Activity $activity)
    {
        $activity->update(['status' => 'todo', 'completed_at' => null]);
        $this->audit($request, 'activity.reopened', $activity);

        return back()->with('success', 'Atividade reaberta.');
    }

    public function activityReschedule(Request $request, Activity $activity)
    {
        $data = $request->validate(['due_at' => ['required', 'date']]);
        $activity->update(['due_at' => $data['due_at']]);
        $this->audit($request, 'activity.rescheduled', $activity);

        return back()->with('success', 'Prazo da atividade atualizado.');
    }

    public function activityAssign(Request $request, Activity $activity)
    {
        $data = $request->validate(['user_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)]]);
        $activity->update(['user_id' => $data['user_id'] ?? null]);
        $this->audit($request, 'activity.assigned', $activity);

        return back()->with('success', 'Responsável atualizado.');
    }

    private function activityData(Request $request): array
    {
        $data = $request->validate(['title' => 'required|string|max:160', 'description' => 'nullable|string|max:10000', 'opportunity_id' => 'nullable|exists:opportunities,id', 'user_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)], 'type' => ['required', Rule::in(['task', 'meeting', 'follow_up'])], 'priority' => ['required', Rule::in(['low', 'normal', 'high'])], 'status' => ['required', Rule::in(['todo', 'in_progress', 'done', 'cancelled'])], 'due_at' => 'nullable|date']);

        return [...$data, 'completed_at' => $data['status'] === 'done' ? now() : null];
    }

    public function activityDestroy(Request $request, Activity $activity)
    {
        $activity->update(['status' => 'cancelled', 'completed_at' => null]);
        $this->audit($request, 'activity.cancelled', $activity);

        return back()->with('success', 'Atividade cancelada; histórico preservado.');
    }
}
