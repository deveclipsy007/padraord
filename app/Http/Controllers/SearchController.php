<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PrototypeCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $needle = mb_strtolower(mb_substr($query, 0, 120));
        $opportunities = $query === '' ? collect() : Opportunity::query()->whereNull('archived_at')
            ->where(fn ($builder) => $builder->whereRaw('LOWER(title) LIKE ?', ["%{$needle}%"])->orWhereRaw('LOWER(client_name) LIKE ?', ["%{$needle}%"]))
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Opportunity $item): array => ['id' => $item->id, 'label' => $item->title, 'detail' => $item->client_name, 'href' => "/opportunities/{$item->id}"])
            ->values();

        $clients = $query === '' ? collect() : Client::query()->whereNull('archived_at')->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])->limit(6)->get()->map(fn (Client $client): array => ['id' => $client->id, 'title' => $client->name, 'subtitle' => $client->industry ?: 'Cliente', 'href' => "/clients/{$client->id}"]);
        $contacts = $query === '' ? collect() : Contact::query()->with('client')->whereNull('archived_at')->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])->limit(6)->get()->map(fn (Contact $contact): array => ['id' => $contact->id, 'title' => $contact->name, 'subtitle' => $contact->client?->name ?: 'Contato', 'href' => "/clients/{$contact->client_id}"]);
        $activities = $query === '' ? collect() : Activity::query()->with('opportunity')->whereRaw('LOWER(title) LIKE ?', ["%{$needle}%"])->limit(6)->get()->map(fn (Activity $activity): array => ['id' => $activity->id, 'title' => $activity->title, 'subtitle' => $activity->opportunity?->title ?: 'Tarefa interna', 'href' => $activity->opportunity ? "/opportunities/{$activity->opportunity_id}" : '/agenda']);
        $prototypes = $query === '' ? collect() : PrototypeCase::whereRaw('LOWER(title) LIKE ?', ["%{$needle}%"])->latest()->limit(4)->get()->map(fn ($case) => ['id' => $case->id, 'label' => $case->title.' · DEMO-'.$case->id, 'detail' => 'Demonstração · dados fictícios', 'href' => '/prototype/'.$case->id.'/overview']);
        $groups = collect([
            ['type' => 'opportunities', 'label' => 'Oportunidades', 'items' => $opportunities->map(fn (array $item): array => ['id' => $item['id'], 'title' => $item['label'], 'subtitle' => $item['detail'], 'href' => $item['href']])->values()],
            ['type' => 'clients', 'label' => 'Clientes', 'items' => $clients->values()],
            ['type' => 'contacts', 'label' => 'Contatos', 'items' => $contacts->values()],
            ['type' => 'activities', 'label' => 'Tarefas', 'items' => $activities->values()],
        ])->filter(fn (array $group): bool => $group['items']->isNotEmpty())->values();

        return response()->json([
            'opportunities' => $opportunities,
            'groups' => $groups,
            'actions' => [
                ['id' => 'new-client', 'title' => 'Novo cliente', 'subtitle' => 'Cadastrar relacionamento', 'href' => '/clients?action=new'],
                ['id' => 'new-opportunity', 'title' => 'Nova oportunidade', 'subtitle' => 'Começar pelo contexto', 'href' => '/?action=new-opportunity'],
                ['id' => 'new-task', 'title' => 'Nova tarefa', 'subtitle' => 'Abrir agenda', 'href' => '/agenda?action=new'],
            ],
            'shortcuts' => array_merge([
                ['label' => 'Hoje', 'detail' => 'Dashboard operacional', 'href' => '/'],
                ['label' => 'Pipeline', 'detail' => 'Fluxo comercial', 'href' => '/pipeline'],
                ['label' => 'Agenda', 'detail' => 'Próximas ações', 'href' => '/agenda'],
                ['label' => 'Ajuda e tour', 'detail' => 'Primeiro ciclo', 'href' => '/help'],
            ], $prototypes->map(fn (array $item): array => ['label' => $item['label'], 'detail' => $item['detail'], 'href' => $item['href']])->values()->all()),
        ]);
    }
}
