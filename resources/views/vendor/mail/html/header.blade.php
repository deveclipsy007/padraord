@props(['url'])
{{-- Marca da casa. Sem imagem externa: cliente de e-mail costuma bloquear
     e o símbolo ✦ já é o usado nas apresentações da Padrão RD. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<span style="font-size: 20px; color: #7657f5; vertical-align: middle;">&#10022;</span>
<span style="font-size: 18px; font-weight: 700; color: #17181d; letter-spacing: -0.2px; vertical-align: middle;">{{ $slot }}</span>
</a>
</td>
</tr>
