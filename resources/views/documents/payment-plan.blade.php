@if(data_get($document->content, 'sources.payment_plan'))
<h2>Condições de pagamento</h2>
@foreach(data_get($document->content, 'sources.payment_plan.installments', []) as $installment)
<p>{{ $installment['sequence'] }}. {{ $installment['label'] }} — R$ {{ number_format($installment['amount_cents']/100,2,',','.') }} · {{ str_replace('_', ' ', $installment['trigger']) }} {{ $installment['offset_days'] !== null ? '('.$installment['offset_days'].' dias)' : '' }} {{ $installment['due_at'] ?? $installment['milestone'] ?? '' }}</p>
@endforeach
<p class="meta">Plano r{{ data_get($document->content, 'sources.payment_plan.revision') }} · {{ data_get($document->content, 'sources.payment_plan.status') === 'accepted' ? 'Aceite registrado' : 'Condições propostas, aguardando aceite' }}</p>
@endif
