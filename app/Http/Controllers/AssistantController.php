<?php

namespace App\Http\Controllers;

use App\AI\ActionPlanner;
use App\Models\AssistantPreview;
use App\Models\Opportunity;
use App\Models\Supplier;
use App\Services\AssistantActions;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
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
