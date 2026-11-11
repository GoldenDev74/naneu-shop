@props(['url'])
{{-- En-tête des e-mails : logo de la boutique, lien vers l'application. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/logo-naneu.png') }}" class="logo" width="180" alt="{{ config('naneu.shop.name', config('app.name')) }}">
</a>
</td>
</tr>
