<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Services\ProductionOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionTaskTemplateController extends Controller
{
    public function store(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $tasks = $opportunity->productionTasks()->orderBy('sort_order')->get();
        if ($opportunity->stage->value !== 'closed' || $tasks->isEmpty() || $tasks->contains(fn ($task) => $task->status !== 'done')) {
            throw ValidationException::withMessages(['source' => 'Conclua o evento e suas tarefas antes de criar um modelo.']);
        }
        $items = $tasks->map(fn ($task): array => [
            'title' => $task->title,
            'description' => $task->description,
            'phase' => $task->phase,
            'priority' => $task->priority,
        ])->values()->all();
        $budget = $opportunity->budgets()->where('status', 'approved')->latest('version')->first();
        $categories = $budget?->items()->distinct()->pluck('category')->filter()->values()->all() ?? [];
        DB::transaction(function () use ($request, $opportunity, $data, $items, $categories): void {
            $templateId = DB::table('production_task_templates')->insertGetId([
                'source_opportunity_id' => $opportunity->id,
                'created_by' => $request->user()->id,
                'name' => trim($data['name']),
                'items' => json_encode($items, JSON_THROW_ON_ERROR),
                'budget_categories' => json_encode($categories, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'production.template_created', 'subject_type' => Opportunity::class, 'subject_id' => $opportunity->id, 'metadata' => ['template_id' => $templateId, 'task_count' => count($items)]]);
        });

        return back()->with('success', 'Modelo criado a partir do evento concluído.');
    }

    public function apply(Request $request, Opportunity $opportunity, int $template, ProductionOperations $operations): RedirectResponse
    {
        $record = DB::table('production_task_templates')->where('id', $template)->first();
        if (! $record) {
            abort(404);
        }
        if ((int) $record->source_opportunity_id === $opportunity->id) {
            throw ValidationException::withMessages(['template' => 'Use este modelo em outro evento.']);
        }
        $items = json_decode($record->items, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($items) || $items === [] || count($items) > 100) {
            throw ValidationException::withMessages(['template' => 'Este modelo não contém uma lista de tarefas válida.']);
        }
        DB::transaction(function () use ($request, $opportunity, $template, $items, $operations): void {
            if (DB::table('production_template_applications')->where('production_task_template_id', $template)->where('opportunity_id', $opportunity->id)->exists()) {
                throw ValidationException::withMessages(['template' => 'Este modelo já foi aplicado neste evento.']);
            }
            foreach ($items as $item) {
                $operations->createTask($opportunity, $request->user(), [
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                    'phase' => $item['phase'] ?? 'preparation',
                    'priority' => $item['priority'] ?? 'normal',
                ]);
            }
            DB::table('production_template_applications')->insert([
                'production_task_template_id' => $template,
                'opportunity_id' => $opportunity->id,
                'applied_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'production.template_applied', 'subject_type' => Opportunity::class, 'subject_id' => $opportunity->id, 'metadata' => ['template_id' => $template, 'task_count' => count($items)]]);
        });

        return back()->with('success', 'Tarefas do modelo adicionadas para revisão neste evento.');
    }
}
