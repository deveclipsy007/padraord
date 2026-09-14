<?php

namespace App\Services;

use App\Models\Opportunity;
use Illuminate\Support\Facades\DB;

final class EventProfitability
{
    public const VARIANCE_LIMIT_BPS = 1000;

    public function for(Opportunity $case): array
    {
        $budget = $case->budgets()->where('status', 'approved')->latest('version')->first();
        $categories = [];
        $quoteTotal = 0;
        foreach ($budget?->items ?? [] as $i) {
            $i->setRelation('budget', $budget);
            $m = Money::breakdown($i);
            $categories[$i->category] = ($categories[$i->category] ?? 0) + $m['cost'] + $m['tax'] + $m['contingency'];
            $quoteTotal += $m['total'];
        }
        $results = DB::table('event_cost_results')->where('opportunity_id', $case->id)->get()->keyBy('category');
        $payables = collect(app(EventFinance::class)->ledger($case, 'payables'))->groupBy('category');
        $rows = [];
        foreach (array_unique([...array_keys($categories), ...$results->keys()->all(), ...$payables->keys()->all()]) as $key) {
            $planned = $categories[$key] ?? 0;
            $r = $results[$key] ?? null;
            $paid = ($payables[$key] ?? collect())->sum('paid_cents');
            $actual = max((int) ($r?->actual_cents ?? 0), $paid);
            $difference = $actual - $planned;
            $threshold = Money::ratio($planned, self::VARIANCE_LIMIT_BPS, 10000);
            $relevant = abs($difference) > $threshold;
            $reason = $r?->variance_reason;
            $rows[] = ['category' => $key, 'planned_cents' => $planned, 'actual_cents' => $actual, 'paid_cents' => $paid, 'difference_cents' => $difference, 'variance_reason' => $reason, 'revision' => $r?->revision ?? 0, 'reconciled' => $r !== null && $r->actual_cents >= $paid, 'requires_reason' => $relevant && blank($reason)];
        }
        $plan = app(EventFinance::class)->plan($case);
        $contracted = $plan && $plan['status'] === 'accepted' ? $plan['total_cents'] : 0;
        $planned = array_sum(array_column($rows, 'planned_cents'));
        $actual = array_sum(array_column($rows, 'actual_cents'));
        $received = DB::table('financial_settlements')->where('opportunity_id', $case->id)->where('ledger', 'receivables')->sum('amount_cents');
        $hasFinance = $plan !== null || $results->isNotEmpty() || $payables->isNotEmpty();
        $closure = [];
        if ($hasFinance) {
            if (! $rows) {
                $closure[] = 'Registre e confira os custos por categoria antes de encerrar.';
            }foreach ($rows as $row) {
                if (! $row['reconciled']) {
                    $closure[] = 'Confira o custo realizado de '.$row['category'].'.';
                }if ($row['requires_reason']) {
                    $closure[] = 'Justifique o desvio superior a 10% em '.$row['category'].'.';
                }
            }
        }

        return ['contracted_revenue_cents' => $contracted, 'budget_revenue_cents' => $quoteTotal, 'received_cents' => (int) $received, 'planned_cost_cents' => $planned, 'actual_cost_cents' => $actual, 'planned_margin_cents' => $contracted - $planned, 'actual_margin_cents' => $contracted - $actual, 'final' => $hasFinance && $case->postEventReport()->where('status', 'closed')->exists() && ! $closure, 'categories' => $rows, 'closure_errors' => $closure, 'variance_limit_bps' => self::VARIANCE_LIMIT_BPS, 'has_finance' => $hasFinance];
    }
}
