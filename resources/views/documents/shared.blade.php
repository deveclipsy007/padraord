<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->title }} · Padrão RD</title>
    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,sans-serif;color:#17181d;background:#edf2f8}body{margin:0;padding:32px 16px}.card{max-width:760px;margin:auto;background:#fff;border:1px solid #dce4ef;border-radius:24px;padding:32px;box-shadow:0 20px 50px #61708d20}h1{margin:8px 0 4px;font-size:32px}h2{font-size:18px;margin-top:28px;color:#4d3db5}.meta{color:#687184;font-size:14px}.notice{padding:12px 14px;border-radius:12px;background:#f0ebff;color:#48359c;margin:20px 0}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}button{min-height:44px;border:0;border-radius:12px;padding:0 18px;font-weight:700;cursor:pointer}.accept{background:#7657f5;color:#fff}.changes{background:#17181d;color:#fff}textarea,input{width:100%;box-sizing:border-box;border:1px solid #cfd8e5;border-radius:12px;padding:12px;font:inherit;margin-top:8px}label{display:block;margin-top:18px;font-weight:600}@media(max-width:600px){body{padding:16px}.card{padding:22px;border-radius:18px}h1{font-size:26px}}
    </style>
</head>
<body>
<main class="card">
    <div class="meta">Padrão RD · {{ $opportunity->client_name }}</div>
    <h1>{{ $document->title }}</h1>
    <p class="meta">Versão {{ $document->version }} · {{ $document->purpose === 'management' ? 'Proposta de Gestão' : 'Proposta de Viabilidade' }}</p>
    @if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
    @if($decision)<p class="notice" role="status">Retorno registrado: {{ $decision->decision === 'accepted' ? 'proposta aceita' : 'ajustes solicitados' }}@if($decision->message) · {{ $decision->message }}@endif</p>@endif
    <p class="notice">Esta é a versão enviada para sua apreciação. O link é válido até {{ \Carbon\Carbon::parse($expiresAt)->format('d/m/Y') }}.</p>
    @foreach(['objective'=>'Objetivo','scope'=>'Escopo','conditions'=>'Condições'] as $key=>$label)<section><h2>{{ $label }}</h2><p style="white-space:pre-wrap">{{ $sections[$key] ?: 'Não informado' }}</p></section>@endforeach
    <h2>Investimento</h2><p>{{ data_get($document->content,'sources.budget.totalCents') !== null ? 'R$ '.number_format(data_get($document->content,'sources.budget.totalCents')/100,2,',','.') : 'A confirmar na revisão comercial.' }}</p>
    @if(!$decision)<form method="post" action="{{ url('/shared/proposal/'.$token.'/decision') }}">
        @csrf
        <label>Seu nome (opcional)<input name="decided_by_name" maxlength="160"></label>
        <label>Mensagem<textarea name="message" maxlength="5000" rows="4" placeholder="Observações ou ajustes desejados"></textarea></label>
        <div class="actions"><button class="accept" name="decision" value="accepted">Aceitar proposta</button><button class="changes" name="decision" value="requested_changes">Pedir ajustes</button></div>
    </form>@else<p class="meta">Este link já recebeu uma decisão e permanece disponível apenas para consulta da versão apresentada.</p>@endif
</main>
</body>
</html>
