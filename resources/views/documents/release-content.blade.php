@php
    $document = data_get($release, 'document', []);
    $sections = data_get($release, 'sections', []);
    $sources = data_get($release, 'sources', []);
    $labels = [
        'objective' => 'Objetivo',
        'scope' => 'Escopo',
        'inclusions' => 'O que está incluído',
        'exclusions' => 'Fora do escopo',
        'clauses' => 'Cláusulas',
        'conditions' => 'Condições',
    ];
@endphp
<style>
    .release-document { color: #1d1d1f; font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; line-height: 1.65; }
    .release-document__header { margin-bottom: 30px; padding-bottom: 16px; border-bottom: 1px solid #d5d5d7; }
    .release-document__brand { color: #6e6e73; font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
    .release-document h1 { margin: 10px 0 6px; color: #1d1d1f; font-size: 27px; letter-spacing: -.03em; line-height: 1.2; }
    .release-document h2 { margin: 24px 0 7px; color: #1d1d1f; font-size: 14px; line-height: 1.3; page-break-after: avoid; }
    .release-document p { margin: 0; white-space: pre-wrap; overflow-wrap: break-word; }
    .release-document__meta { color: #6e6e73; font-size: 10px; }
    .release-document__notice { margin-top: 12px; padding: 9px 11px; border-left: 3px solid #1d1d1f; background: #f5f5f7; color: #424245; font-size: 10px; }
    .release-document__payments { margin-top: 24px; padding-top: 14px; border-top: 1px solid #d5d5d7; }
    .release-document__payment { margin-top: 5px; color: #424245; }
    .release-document__footer { margin-top: 34px; padding-top: 12px; border-top: 1px solid #d5d5d7; color: #6e6e73; font-size: 9px; }
</style>
<article class="release-document" data-release-schema="{{ data_get($release, 'schema') }}">
    <header class="release-document__header">
        <div class="release-document__brand">Padrão RD · versão liberada</div>
        <h1>{{ data_get($document, 'title') }}</h1>
        <p class="release-document__meta">
            {{ data_get($sources, 'case_title') }} · {{ data_get($sources, 'client') }} · v{{ data_get($document, 'version') }}
        </p>
        <p class="release-document__notice">
            {{ data_get($document, 'type') === 'contract' ? 'Contrato liberado para assinatura.' : 'Proposta liberada para decisão.' }}
        </p>
    </header>

    @foreach($labels as $key => $label)
        @if(filled(data_get($sections, $key)))
            <section>
                <h2>{{ $label }}</h2>
                <p>{{ data_get($sections, $key) }}</p>
            </section>
        @endif
    @endforeach

    @if(data_get($sources, 'budget.totalCents') !== null)
        <section class="release-document__payments">
            <h2>Investimento</h2>
            <p>R$ {{ number_format(data_get($sources, 'budget.totalCents') / 100, 2, ',', '.') }}</p>
            @if(data_get($sources, 'payment_plan'))
                <h2>Condições de pagamento</h2>
                @foreach(data_get($sources, 'payment_plan.installments', []) as $installment)
                    <p class="release-document__payment">
                        {{ $installment['sequence'] }}. {{ $installment['label'] }} — R$ {{ number_format($installment['amount_cents'] / 100, 2, ',', '.') }} ·
                        {{ str_replace('_', ' ', $installment['trigger']) }}
                        {{ $installment['due_at'] ?? $installment['milestone'] ?? '' }}
                    </p>
                @endforeach
                <p class="release-document__meta">Plano r{{ data_get($sources, 'payment_plan.revision') }} · {{ data_get($sources, 'payment_plan.status') === 'accepted' ? 'Aceite registrado' : 'Condições propostas' }}</p>
            @endif
        </section>
    @endif

    <footer class="release-document__footer">
        Fontes: briefing r{{ data_get($sources, 'briefing_revision', '—') }} · orçamento #{{ data_get($sources, 'budget_id', '—') }} r{{ data_get($sources, 'budget_revision', '—') }} ·
        apresentação {{ data_get($release, 'presentation.template') }}.
    </footer>
</article>
