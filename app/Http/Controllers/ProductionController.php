<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\ProductionTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductionController extends Controller
{
    public function show(Opportunity $opportunity): Response
    {
        return Inertia::render('Production', [
            'opportunity' => ['id' => $opportunity->id, 'title' => $opportunity->title, 'clientName' => $opportunity->client_name, 'eventDate' => $opportunity->event_date?->format('d/m/Y')],
            'tasks' => $opportunity->productionTasks()->orderBy('status')->orderBy('due_date')->get()->map(fn (ProductionTask $task): array => [
                'id' => $task->id, 'title' => $task->title, 'description' => $task->description, 'status' => $task->status, 'priority' => $task->priority, 'dueDate' => $task->due_date?->format('Y-m-d'),
            ])->values(),
        ]);
    }

    public function storeTask(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'in:low,normal,high'],
            'due_date' => ['nullable', 'date'],
        ]);
        $opportunity->productionTasks()->create($data + ['status' => 'todo']);

        return back()->with('success', 'Tarefa criada na produção.');
    }

    public function updateTask(Request $request, ProductionTask $task): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:todo,in_progress,done,blocked']]);
        $task->update($data);

        return back()->with('success', 'Status da tarefa atualizado.');
    }
}
