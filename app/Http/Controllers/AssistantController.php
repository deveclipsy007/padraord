<?php

namespace App\Http\Controllers;

use App\AI\ActionPlanner;
use App\AI\AiConfiguration;
use App\AI\AssistantChat;
use App\Enums\Ability;
use App\Models\AssistantPreview;
use App\Models\Opportunity;
use App\Models\Supplier;
use App\Services\AssistantActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssistantController extends Controller
{
    public function chatHistory(Request $request, AssistantChat $chat, AiConfiguration $config)
    {
        return response()->json(['turns' => $chat->history($request->user()), 'settings' => $config->publicState(),
            'canConfigure' => $request->user()->can(Ability::ManageAi->value)]);
    }

    public function chat(Request $request, AssistantChat $chat)
    {
        return response()->json($chat->send($request->user(), $request->all()));
    }

    public function editChatPreview(Request $request, int $turn, AssistantActions $actions)
    {
        $request->validate(['preview_id' => 'required|integer', 'actions' => 'required|array']);

        return DB::transaction(function () use ($request, $turn, $actions) {
            $query = DB::table('assistant_chat_turns')->where('id', $turn)->where('user_id', $request->user()->id);
            abort_unless((clone $query)->exists(), 404);
            $query->update(['updated_at' => now()]);
            $record = $query->first();
            $result = json_decode($record->result, true);
            $id = data_get($result, 'preview.id');
            abort_unless($id === (int) $request->preview_id, 409);
            $old = AssistantPreview::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($old->user_id === $request->user()->id && $old->status === 'preview', 409);
            $preview = $actions->preview($request->user(), ['message' => $record->message, 'opportunity_id' => $old->context['opportunity_id'],
                'supplier_id' => $old->context['supplier_id'], 'actions' => $request->input('actions')]);
            // Editing a demonstration never promotes it to an operational action.
            $preview->update(['mode' => $old->mode]);
            $old->update(['status' => 'superseded']);
            $result['preview'] = $preview->toArray();
            $query->update(['result' => json_encode($result)]);

            return response()->json($preview);
        });
    }

    public function interpret(Request $r, ActionPlanner $planner)
    {
        return response()->json($planner->interpret($r->all(), $r->user()));
    }

    public function context()
    {
        return response()->json(['opportunities' => Opportunity::orderBy('title')->get(['id', 'title']), 'suppliers' => Supplier::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $r, AssistantActions $actions)
    {
        return response()->json($actions->preview($r->user(), $r->all()), 201);
    }

    public function show(Request $r, AssistantPreview $preview)
    {
        abort_unless($preview->user_id === $r->user()->id, 403);

        return response()->json($preview);
    }

    public function confirm(Request $r, AssistantPreview $preview, AssistantActions $actions)
    {
        return response()->json($actions->confirm($preview, $r->user()));
    }
}
