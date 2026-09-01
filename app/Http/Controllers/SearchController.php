<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\PrototypeCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $opportunities = $query === '' ? collect() : Opportunity::query()
            ->where(fn ($builder) => $builder->where('title', 'like', "%{$query}%")->orWhere('client_name', 'like', "%{$query}%"))
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Opportunity $item): array => ['id' => $item->id, 'label' => $item->title, 'detail' => $item->client_name, 'href' => "/opportunities/{$item->id}"])
            ->values();

        if ($query !== '') {
            $opportunities = $opportunities->concat(PrototypeCase::where('title', 'like', '%'.$query.'%')->latest()->limit(4)->get()->map(fn ($case) => ['id' => $case->id, 'label' => $case->title.' · DEMO-'.$case->id, 'detail' => 'Demonstração · dados fictícios', 'href' => '/prototype/'.$case->id.'/overview']))->values();
        }

        return response()->json([
            'opportunities' => $opportunities,
            'shortcuts' => [
                ['label' => 'Hoje', 'detail' => 'Dashboard operacional', 'href' => '/'],
                ['label' => 'Pipeline', 'detail' => 'Fluxo comercial', 'href' => '/pipeline'],
                ['label' => 'Agenda', 'detail' => 'Próximas ações', 'href' => '/agenda'],
                ['label' => 'Ajuda e tour', 'detail' => 'Primeiro ciclo', 'href' => '/help'],
            ],
        ]);
    }
}
