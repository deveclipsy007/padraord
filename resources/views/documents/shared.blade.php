<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->title }} · Padrão RD</title>
    <style>
        :root{font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text",system-ui,sans-serif;color:#1d1d1f;background:#f5f5f7}body{margin:0;padding:32px 16px}.card{max-width:840px;margin:auto;background:#fff;border:1px solid #d5d5d7;border-radius:18px;padding:clamp(22px,5vw,48px);box-shadow:0 18px 42px rgba(0,0,0,.08)}.meta{color:#6e6e73;font-size:13px}.notice{padding:12px 14px;border-left:3px solid #1d1d1f;background:#f5f5f7;color:#424245;margin:20px 0}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:28px}button{min-height:42px;border:1px solid #1d1d1f;border-radius:10px;padding:0 16px;font-weight:650;cursor:pointer}.accept{background:#1d1d1f;color:#fff}.changes{background:#fff;color:#1d1d1f}textarea,input{width:100%;box-sizing:border-box;border:1px solid #d5d5d7;border-radius:10px;padding:12px;font:inherit;margin-top:8px}label{display:block;margin-top:18px;font-weight:600}@media(max-width:600px){body{padding:12px}.card{padding:22px;border-radius:14px}}
    </style>
</head>
<body>
<main class="card">
    @if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
    @if($decision)<p class="notice" role="status">Retorno registrado: {{ $decision->decision === 'accepted' ? 'proposta aceita' : 'ajustes solicitados' }}@if($decision->message) · {{ $decision->message }}@endif</p>@endif
    <p class="notice">Esta é a versão enviada para sua apreciação. O link é válido até {{ \Carbon\Carbon::parse($expiresAt)->format('d/m/Y') }}.</p>
    {!! $release['html'] !!}
    @if(!$decision)<form method="post" action="{{ url('/shared/proposal/'.$token.'/decision') }}">
        @csrf
        <label>Seu nome (opcional)<input name="decided_by_name" maxlength="160"></label>
        <label>Mensagem<textarea name="message" maxlength="5000" rows="4" placeholder="Observações ou ajustes desejados"></textarea></label>
        <div class="actions"><button class="accept" name="decision" value="accepted">Aceitar proposta</button><button class="changes" name="decision" value="requested_changes">Pedir ajustes</button></div>
    </form>@else<p class="meta">Este link já recebeu uma decisão e permanece disponível apenas para consulta da versão apresentada.</p>@endif
</main>
</body>
</html>
