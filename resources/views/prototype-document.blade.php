<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Demonstração · {{ $case->title }}</title><style>body{font:16px/1.6 system-ui;margin:40px auto;max-width:800px;padding:24px;color:#24232b}h1{font-size:28px}.notice{border:2px solid #7d56ba;padding:16px}td,th{padding:12px;border-bottom:1px solid #ddd;text-align:left}table{width:100%;border-collapse:collapse}pre{white-space:pre-wrap;font:inherit}button{padding:12px 20px}@media print{button{display:none}.notice{display:block}}</style></head><body>
<p class="notice">DEMONSTRAÇÃO — DOCUMENTO FICTÍCIO, SEM VALIDADE COMERCIAL OU CONTRATUAL</p>
<h1>Padrão RD · {{ $case->title }}</h1><p>{{ $document['type'] === 'contract' ? 'Estrutura demonstrativa de contrato — modelo jurídico pendente' : 'Proposta demonstrativa' }} · {{ $document['purpose'] === 'management' ? 'Gestão' : 'Viabilidade' }} · v{{ $document['version'] }}</p>
<p>Referência do caso DEMO-{{ $case->id }} · Orçamento v{{ $document['budget']['version'] }}</p>
@if($document['stale'])<p class="notice">Versão desatualizada — preservada apenas para histórico.</p>@endif
<h2>Contexto preservado</h2>@foreach($document['briefing'] as $field => $value)<p><strong>{{ $field }}</strong>: {{ $value }}</p>@endforeach
<h2>Escopo e condições demonstrativas</h2><pre>{{ $document['notes'] }}</pre>
<h2>Orçamento de demonstração</h2><table><thead><tr><th>Item</th><th>Quantidade</th><th>Total</th></tr></thead><tbody>@foreach($document['budget']['items'] as $item)<tr><td>{{ $item['description'] }}</td><td>{{ $item['quantity'] }}</td><td>R$ {{ number_format($item['totalCents']/100,2,',','.') }}</td></tr>@endforeach</tbody></table><p><strong>Total: R$ {{ number_format($document['budget']['totalCents']/100,2,',','.') }}</strong></p>
<p>Conteúdo congelado na criação desta versão. Taxas simuladas; nenhuma proposta foi enviada pelo sistema.</p><button onclick="window.print()">Imprimir / salvar PDF pelo navegador</button>
</body></html>
