<?php

namespace App\Http\Controllers;

use App\Enums\Ability;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
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

    public function clientStore(Request $request)
    {
        $client = Client::create($this->clientData($request));
        $this->audit($request, 'client.created', $client);

        return redirect("/clients/{$client->id}")->with('success', 'Cliente criado.');
    }

    public function clientUpdate(Request $request, Client $client)
    {
        $client->update($this->clientData($request));
        $this->audit($request, 'client.updated', $client);

        return back()->with('success', 'Cliente atualizado.');
    }

    private function clientData(Request $request): array
    {
        return $request->validate(['name' => 'required|string|max:160', 'industry' => 'nullable|string|max:160', 'notes' => 'nullable|string|max:10000']);
    }

    public function clientShow(Client $client)
    {
        return Inertia::render('ClientProfile', ['client' => $client->load(['contacts', 'opportunities'])]);
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

    public function contactStore(Request $request, Client $client)
    {
        $contact = $client->contacts()->create($this->contactData($request));
        $this->audit($request, 'contact.created', $contact);

        return back()->with('success', 'Contato adicionado.');
    }

    public function contactUpdate(Request $request, Client $client, Contact $contact)
    {
        abort_unless($contact->client_id === $client->id, 404);
        $contact->update($this->contactData($request));
        $this->audit($request, 'contact.updated', $contact);

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
        return $request->validate(['name' => 'required|string|max:160', 'email' => 'nullable|email|max:160', 'phone' => 'nullable|string|max:60', 'role' => 'nullable|string|max:160']);
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
