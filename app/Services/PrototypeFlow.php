<?php

namespace App\Services;

use App\AI\DemoAiProvider;
use App\Models\PrototypeCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PrototypeFlow
{
    public const SECTIONS = ['overview', 'commercial', 'briefing', 'viability', 'suppliers', 'budget', 'documents', 'production', 'post-event', 'history'];

    public const FIELDS = ['objective', 'audience', 'date', 'location', 'investment', 'scope', 'restrictions', 'references'];

    public static function initial(User $user): array
    {
        return ['client' => 'Horizonte Educação · fictício', 'owner' => $user->name, 'origin' => 'Indicação', 'priority' => 'normal', 'due' => today()->addDays(14)->format('Y-m-d'), 'next' => 'Revisar o briefing inicial', 'stage' => 'lead', 'viability' => 'not_contracted', 'modality' => 'express', 'management' => 'not_contracted', 'outcome' => null, 'messages' => [], 'briefing' => array_fill_keys(self::FIELDS, ''), 'briefingApproved' => false, 'suggestions' => [], 'quotes' => [], 'items' => [], 'budgetStatus' => 'draft', 'budgetVersion' => 1, 'budgetSnapshots' => [], 'documents' => [], 'technical' => ['description' => '', 'quantity' => 0, 'confirmed' => false, 'evidence' => ''], 'tasks' => [], 'occurrences' => [], 'impacts' => [], 'deliverables' => [], 'history' => [], 'completedSteps' => [], 'post' => ['summary' => '', 'learning' => '', 'actualCents' => 0, 'rating' => 0]];
    }

    public function apply(PrototypeCase $case, User $user, array $data): void
    {
        DB::transaction(function () use ($case, $user, $data) {
            $case = PrototypeCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $this->require($case->revision === (int) $data['revision'], 'O caso mudou. Atualize a página antes de reaplicar sua ação.', 'revision');
            $s = $case->state;
            $action = $data['action'];
            $this->require(! $s['outcome'], 'Este cenário foi encerrado. Crie outro teste para repetir a jornada.');
            $id = (int) ($data['id'] ?? 0);
            $evidence = trim((string) ($data['evidence'] ?? ''));
            switch ($action) {
                case 'case_save':
                    foreach (['client', 'owner', 'origin', 'priority', 'due', 'next'] as $field) {
                        if (isset($data[$field])) {
                            $s[$field] = $data[$field];
                        }
                    }
                    break;
                case 'stage':
                    $stages = ['lead', 'qualification', 'briefing'];
                    $target = $data['stage'];
                    $this->require(in_array($target, [...$stages, 'lost', 'cancelled'], true), 'Etapa comercial inválida.');
                    if (in_array($target, ['lost', 'cancelled']) || array_search($target, $stages) < array_search($s['stage'], $stages)) {
                        $this->require($evidence !== '', 'Registre o motivo do retorno, perda ou cancelamento.', 'evidence');
                    }
                    $s['stage'] = $target;
                    if (in_array($target, ['lost', 'cancelled'])) {
                        $s['outcome'] = $target;
                    }
                    break;
                case 'message':
                    $text = trim($data['text']);
                    $this->require($text !== '', 'Digite o contexto.', 'text');
                    $messageId = count($s['messages']) + 1;
                    $s['messages'][] = ['id' => $messageId, 'body' => $text, 'author' => $user->name, 'at' => now()->toIso8601String()];
                    $context = $s['briefing'];
                    $context['event_date'] = $context['date'];
                    $context['budget'] = $context['investment'];
                    // Explicit demo adapter: sandbox data never uses the configured paid provider.
                    $result = (new DemoAiProvider)->analyzeBriefing($text, ['briefing' => $context]);
                    foreach ($result->payload['suggested_changes'] as $change) {
                        $field = match ($change['field']) {
                            'event_date' => 'date','budget' => 'investment',default => $change['field']
                        };
                        $s['suggestions'][] = ['id' => count($s['suggestions']) + 1, 'messageId' => $messageId, 'field' => $field, 'current' => $s['briefing'][$field], 'suggested' => $change['suggested'], 'status' => 'pending'];
                    }
                    $s['briefingApproved'] = false;
                    break;
                case 'briefing_save':
                    foreach (self::FIELDS as $field) {
                        if (array_key_exists($field, $data['briefing'] ?? [])) {
                            $s['briefing'][$field] = $data['briefing'][$field] ?? '';
                        }
                    }
                    $s['briefingApproved'] = false;
                    $this->invalidate($s, 'Briefing alterado: revise orçamento e documentos.');
                    break;
                case 'suggestion':
                    $found = false;
                    foreach ($s['suggestions'] as &$suggestion) {
                        if ($suggestion['id'] !== $id) {
                            continue;
                        }
                        $found = true;
                        $this->require($suggestion['status'] === 'pending', 'Esta sugestão já foi revisada.');
                        if ($data['decision'] === 'accepted') {
                            $this->require($suggestion['current'] === $s['briefing'][$suggestion['field']], 'O campo mudou desde a sugestão. Rejeite esta sugestão e edite manualmente.');
                            $s['briefing'][$suggestion['field']] = $suggestion['suggested'];
                            $s['briefingApproved'] = false;
                            $this->invalidate($s, 'Sugestão aceita: revisar o impacto no escopo.');
                        }
                        $suggestion['status'] = $data['decision'];
                    }
                    unset($suggestion);
                    $this->require($found, 'Sugestão não encontrada.');
                    break;
                case 'briefing_approve':
                    foreach (['objective', 'audience', 'date', 'location', 'investment', 'scope'] as $field) {
                        $this->require(filled($s['briefing'][$field]), 'Complete os campos essenciais antes de revisar: '.$field);
                    }
                    $s['briefingApproved'] = true;
                    break;
                case 'contract_viability':
                    $this->require($s['viability'] === 'not_contracted', 'A contratação já está registrada.');
                    $this->require($evidence !== '', 'Informe a evidência simulada de contratação.', 'evidence');
                    $s['modality'] = $data['modality'] ?? 'express';
                    $s['viability'] = 'in_progress';
                    break;
                case 'deliver_viability':
                    $this->require($s['viability'] === 'in_progress', 'Registre a contratação da Viabilidade primeiro.');
                    $this->require($evidence !== '', 'Registre a referência da entrega.', 'evidence');
                    $this->require(! array_diff(CaseJourneyService::required($s['modality']), $data['deliverables'] ?? []), 'Conclua os entregáveis da modalidade.');
                    $s['deliverables'] = $data['deliverables'];
                    $s['viability'] = 'delivered';
                    break;
                case 'accept_viability':
                    $this->require($s['viability'] === 'delivered' && $evidence !== '', 'Registre a entrega e sua evidência de aceite.');
                    $s['viability'] = 'accepted';
                    break;
                case 'close_viability':
                    $this->require($s['viability'] === 'accepted' && $s['management'] === 'not_contracted' && $evidence !== '', 'É necessário aceite da Viabilidade e justificativa do encerramento.');
                    $s['outcome'] = 'viability_completed';
                    break;
                case 'quote':
                    $s['quotes'][] = ['id' => count($s['quotes']) + 1, 'supplier' => $data['supplier'], 'service' => $data['description'], 'costCents' => Money::decimal($data['cost'], 'cost'), 'validUntil' => $data['valid_until'], 'conditions' => $data['conditions'] ?? '', 'evidence' => $evidence];
                    break;
                case 'item':
                    $this->require($s['budgetStatus'] !== 'reviewed', 'Crie uma nova versão para alterar o orçamento revisado.');
                    $quote = collect($s['quotes'])->firstWhere('id', (int) ($data['quote_id'] ?? 0));
                    if (! empty($data['quote_id'])) {
                        $this->require($quote !== null, 'Cotação não encontrada.');
                    }
                    $cost = $quote ? $quote['costCents'] : Money::decimal($data['cost'], 'cost');
                    $quantity = Money::decimal((string) $data['quantity'], 'quantity');
                    $this->require($cost > 0 && $cost <= 100000000 && $quantity > 0 && $quantity <= 1000000, 'Verifique custo e quantidade.');
                    $base = Money::ratio($cost, $quantity, 100);
                    $management = Money::ratio($base, Money::decimal((string) ($data['management_percent'] ?? '0')), 10000);
                    $admin = Money::ratio($base + $management, Money::decimal((string) ($data['administration_percent'] ?? '0')), 10000);
                    $s['items'][] = ['id' => count($s['items']) ? max(array_column($s['items'], 'id')) + 1 : 1, 'description' => $data['description'], 'quantity' => $quantity / 100, 'costCents' => $cost, 'quoteId' => $quote['id'] ?? null, 'supplier' => $quote['supplier'] ?? 'Estimativa manual', 'baseCents' => $base, 'managementCents' => $management, 'administrationCents' => $admin, 'totalCents' => $base + $management + $admin];
                    $this->invalidate($s, 'Orçamento alterado: revisar proposta.');
                    break;
                case 'item_remove':
                    $this->require($s['budgetStatus'] !== 'reviewed', 'Crie uma nova versão para remover itens.');
                    $this->require(collect($s['items'])->contains('id', $id), 'Item não encontrado.');
                    $s['items'] = array_values(array_filter($s['items'], fn ($item) => $item['id'] !== $id));
                    $this->invalidate($s, 'Item removido: revisar documentos.');
                    break;
                case 'budget_version':
                    $s['budgetVersion']++;
                    $s['budgetStatus'] = 'draft';
                    $this->invalidate($s, 'Nova versão do orçamento: documentos anteriores preservados.');
                    break;
                case 'budget_review':
                    $this->require($s['briefingApproved'] && count($s['items']) > 0, 'Revise o briefing e adicione os custos antes de revisar o orçamento.');
                    foreach ($s['items'] as $item) {
                        if ($item['quoteId']) {
                            $quote = collect($s['quotes'])->firstWhere('id', $item['quoteId']);
                            $this->require($quote && $quote['validUntil'] >= today()->format('Y-m-d'), 'Cotação vencida: registre nova cotação e substitua o item.');
                        }
                    }
                    $this->require($s['budgetStatus'] !== 'reviewed', 'Esta versão já foi revisada.');
                    $s['budgetStatus'] = 'reviewed';
                    $s['budgetSnapshots'][] = ['version' => $s['budgetVersion'], 'items' => $s['items'], 'totalCents' => array_sum(array_column($s['items'], 'totalCents')), 'mode' => 'demo'];
                    break;
                case 'document':
                    $this->require($s['budgetStatus'] === 'reviewed' && $s['briefingApproved'], 'Revise briefing e orçamento antes de gerar o documento.');
                    $s['documents'][] = ['id' => count($s['documents']) + 1, 'type' => $data['document_type'], 'purpose' => $data['purpose'], 'version' => count($s['documents']) + 1, 'status' => 'review', 'mode' => 'demo', 'notes' => $data['text'] ?? '', 'briefing' => $s['briefing'], 'budget' => end($s['budgetSnapshots']), 'createdAt' => now()->toIso8601String(), 'stale' => false];
                    break;
                case 'document_review': case 'document_send': case 'document_accept':
                    $found = false;
                    foreach ($s['documents'] as &$document) {
                        if ($document['id'] === $id) {
                            $found = true;
                            $this->require(! $document['stale'], 'Documento desatualizado: gere uma nova versão após revisar as alterações.');
                            $expected = ['document_review' => 'review', 'document_send' => 'approved_demo', 'document_accept' => 'sent_demo'][$action];
                            $this->require($document['status'] === $expected, 'Conclua a revisão e o envio na ordem indicada.');
                            $this->require($evidence !== '', 'Registre a decisão e evidência simulada.', 'evidence');
                            $document['status'] = ['document_review' => 'approved_demo', 'document_send' => 'sent_demo', 'document_accept' => 'accepted_demo'][$action];
                        }
                    }
                    unset($document);
                    $this->require($found, 'Documento não encontrado.');
                    break;
                case 'contract_management':
                    $this->require($s['viability'] === 'accepted' && $s['management'] === 'not_contracted' && $evidence !== '', 'Conclua a Viabilidade e registre a contratação de Gestão.');
                    $this->require(collect($s['documents'])->contains(fn ($d) => $d['purpose'] === 'management' && $d['status'] === 'accepted_demo' && ! $d['stale']), 'Prepare, revise e registre o aceite da proposta de Gestão.');
                    $s['management'] = 'planning';
                    break;
                case 'technical_save':
                    $s['technical'] = ['description' => $data['description'], 'quantity' => $data['quantity'], 'confirmed' => false, 'evidence' => ''];
                    $this->invalidate($s, 'Medidas/quantidades alteradas: reconfirmar fornecedor, orçamento e documento.');
                    break;
                case 'technical_confirm':
                    $this->require($s['technical']['description'] !== '' && $evidence !== '', 'Registre medidas e a evidência da reconfirmação.');
                    $s['technical']['confirmed'] = true;
                    $s['technical']['evidence'] = $evidence;
                    break;
                case 'task':
                    $dependency = (int) ($data['dependency'] ?? 0);
                    if ($dependency) {
                        $this->require(collect($s['tasks'])->contains('id', $dependency), 'Dependência não encontrada neste caso.');
                    }
                    $s['tasks'][] = ['id' => count($s['tasks']) + 1, 'title' => $data['text'], 'owner' => $data['owner'], 'due' => $data['due'], 'phase' => $data['phase'], 'status' => 'todo', 'dependency' => $dependency, 'priority' => $data['priority'] ?? 'normal'];
                    break;
                case 'task_done':
                    $found = false;
                    foreach ($s['tasks'] as &$task) {
                        if ($task['id'] === $id) {
                            $found = true;
                            if ($task['dependency']) {
                                $this->require(collect($s['tasks'])->firstWhere('id', $task['dependency'])['status'] === 'done', 'Conclua a tarefa anterior primeiro.');
                            }
                            $task['status'] = 'done';
                        }
                    }
                    unset($task);
                    $this->require($found, 'Tarefa não encontrada.');
                    break;
                case 'execution':
                    $this->require($s['management'] === 'planning' && $s['technical']['confirmed'] && $s['budgetStatus'] === 'reviewed', 'Contrate a Gestão, revise o orçamento e reconfirme os itens técnicos antes de liberar montagem.');
                    $this->require(collect($s['documents'])->contains(fn ($d) => $d['purpose'] === 'management' && $d['status'] === 'accepted_demo' && ! $d['stale']), 'A proposta de Gestão foi alterada: registre nova revisão e aceite antes de liberar.');
                    $s['management'] = 'execution';
                    break;
                case 'post_start':
                    $this->require($s['management'] === 'execution', 'Inicie a operação antes de passar ao pós-evento.');
                    $s['management'] = 'post_event';
                    break;
                case 'occurrence':
                    $s['occurrences'][] = ['description' => $data['text'], 'solution' => $data['solution'], 'extraCents' => Money::decimal($data['cost'] ?? '0', 'cost'), 'at' => now()->toIso8601String()];
                    break;
                case 'post_save':
                    $s['post'] = ['summary' => $data['text'], 'learning' => $data['learning'], 'actualCents' => Money::decimal($data['cost'], 'cost'), 'rating' => (int) $data['rating']];
                    break;
                case 'close_event':
                    $this->require($s['management'] === 'post_event' && filled($s['post']['summary']) && filled($s['post']['learning']), 'Registre o pós-evento e seus aprendizados antes de encerrar.');
                    $this->require(! collect($s['tasks'])->contains(fn ($task) => $task['status'] !== 'done'), 'Conclua as tarefas pendentes antes de encerrar.');
                    $this->require($s['technical']['confirmed'] && $s['budgetStatus'] === 'reviewed', 'Existem alterações técnicas ou de orçamento aguardando revisão.');
                    $s['outcome'] = 'event_completed';
                    break;
                default: $this->require(false, 'Ação desconhecida.');
            }
            $s['completedSteps'] = array_values(array_unique([...$s['completedSteps'], $action]));
            $s['history'][] = ['action' => $action, 'user' => $user->name, 'at' => now()->toIso8601String(), 'evidence' => $evidence, 'revision' => $case->revision + 1, 'mode' => 'demo'];
            $updated = PrototypeCase::whereKey($case->id)->where('revision', $data['revision'])->update(['state' => json_encode($s), 'revision' => $case->revision + 1, 'updated_at' => now()]);
            $this->require($updated === 1, 'Conflito de edição. Atualize o cenário.', 'revision');
        });
    }

    private function invalidate(array &$state, string $reason): void
    {
        if ($state['budgetStatus'] === 'reviewed') {
            $state['budgetVersion']++;
        }
        $state['budgetStatus'] = 'draft';
        foreach ($state['documents'] as &$document) {
            $document['stale'] = true;
        }
        $state['impacts'][] = $reason;
    }

    private function require(bool $condition, string $message, string $field = 'action'): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
