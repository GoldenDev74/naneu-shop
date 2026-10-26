@use('App\Support\Money')
{{--
    Ticket de caisse 80 mm (zone imprimable ~72 mm), comme ceux des
    pharmacies et restaurants. Un seul gabarit pour le PDF (dompdf) et
    pour l'impression directe depuis le navigateur ($pdf = false).
    Noir sur blanc uniquement : une imprimante thermique n'a pas de gris.
    Un long ticket est découpé en pages ($pages) : la première porte le
    logo, les suivantes un rappel de la référence ; seule la dernière a
    le total, les autres un sous-total cumulé « à reporter ».
--}}
@php
    $qte = fn (float $q) => rtrim(rtrim(Money::number($q, 3), '0'), ',.');
    $nbArticles = $vente->lines->sum(fn ($l) => (float) $l->quantity);
    $nbPages = $pages->count();
    $cumul = 0;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Ticket') }} {{ $vente->reference }}</title>
    <style>
        @page { size: 80mm {{ $pdf ? $hauteurMm.'mm' : 'auto' }}; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        body { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; font-size: 8.4pt; line-height: 1.35; }
        .ticket { width: 72mm; margin: 0 auto; padding: 4mm 0 6mm; }
        .centre { text-align: center; }
        .logo { width: 44mm; margin: 0 auto 2mm; display: block; }
        .petit { font-size: 7.4pt; }
        .gras { font-weight: bold; }
        .sep { border: 0; border-top: 1px dashed #000; margin: 2.2mm 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0; vertical-align: top; }
        .d { text-align: right; white-space: nowrap; }
        .article td { padding-top: 1.2mm; }
        .detail td { padding-bottom: 0.4mm; }
        .total td { font-size: 11.5pt; font-weight: bold; padding: 1mm 0; }
        .page-ticket { page-break-after: always; break-after: page; }
        .page-ticket:last-child { page-break-after: auto; break-after: auto; }
        .suite { text-align: center; font-size: 7.4pt; margin-top: 1.5mm; }
        .annule { border: 2px solid #000; text-align: center; font-weight: bold; font-size: 12pt; padding: 1mm; margin: 2mm 0; letter-spacing: 2px; }
        @unless ($pdf)
        @media screen {
            html, body { background: #e9efeb; min-height: 100%; } body { padding: 16px 0; }
            .ticket { background: #fff; padding: 6mm 4mm 8mm; width: 80mm; box-shadow: 0 6px 24px rgba(0,0,0,.12); }
            .page-ticket + .page-ticket { margin-top: 14px; }
            .barre { width: 80mm; margin: 0 auto 12px; display: flex; gap: 8px; font-family: system-ui, sans-serif; }
            .barre button, .barre a { flex: 1; text-align: center; padding: 10px; border-radius: 999px; border: 1px solid #23804f; background: #23804f; color: #fff; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; }
            .barre a { background: #fff; color: #23804f; }
        }
        @media print { .barre { display: none; } body { background: #fff; padding: 0; } .ticket { box-shadow: none; padding: 2mm 0 6mm; } .page-ticket + .page-ticket { margin-top: 0; } }
        @endunless
    </style>
</head>
<body>
    @unless ($pdf)
        <div class="barre">
            <button type="button" onclick="window.print()">{{ __('Imprimer') }}</button>
            <a href="{{ route('caisse.ticket.pdf', $vente) }}">{{ __('PDF') }}</a>
        </div>
    @endunless

    @foreach ($pages as $p => $lignesPage)
        @php $derniere = $p === $nbPages - 1; @endphp
        <div class="ticket page-ticket">
            @if ($p === 0)
                <div class="centre">
                    <img class="logo" src="{{ $logo }}" alt="{{ config('naneu.shop.name') }}">
                    @if (config('naneu.shop.address')) <div class="petit">{{ config('naneu.shop.address') }}</div> @endif
                    @if (config('naneu.shop.phone')) <div class="petit">{{ __('Tél.') }} {{ config('naneu.shop.phone') }}</div> @endif
                    @if (config('naneu.shop.nif')) <div class="petit">{{ __('NIF') }} {{ config('naneu.shop.nif') }}</div> @endif
                </div>

                <hr class="sep">

                <table class="petit">
                    <tr><td>{{ __('Ticket') }}</td><td class="d gras">{{ $vente->reference }}</td></tr>
                    <tr><td>{{ __('Date') }}</td><td class="d">{{ $vente->created_at->format('d/m/Y H:i') }}</td></tr>
                    <tr><td>{{ __('Caisse') }}</td><td class="d">{{ $vente->user?->name }}</td></tr>
                    @if ($nbPages > 1)
                        <tr><td>{{ __('Page') }}</td><td class="d">1/{{ $nbPages }}</td></tr>
                    @endif
                </table>
            @else
                <div class="centre gras">{{ config('naneu.shop.name') }}</div>
                <table class="petit" style="margin-top: 1mm">
                    <tr><td>{{ __('Ticket') }}</td><td class="d gras">{{ $vente->reference }}</td></tr>
                    <tr><td>{{ __('Page') }}</td><td class="d">{{ $p + 1 }}/{{ $nbPages }}</td></tr>
                </table>
            @endif

            @if ($vente->status === \App\Enums\DocumentStatus::Annule)
                <div class="annule">{{ __('ANNULÉ') }}</div>
            @endif

            <hr class="sep">

            @if ($p > 0)
                <table class="petit">
                    <tr><td>{{ __('Report') }}</td><td class="d">{{ Money::format($cumul) }}</td></tr>
                </table>
            @endif

            <table>
                @foreach ($lignesPage as $ligne)
                    @php $cumul += $ligne->line_total; @endphp
                    <tr class="article"><td colspan="2" class="gras">{{ $ligne->product?->name ?? __('Produit supprimé') }}</td></tr>
                    <tr class="detail">
                        <td>{{ $qte((float) $ligne->quantity) }} {{ $ligne->product?->unit?->symbol }} x {{ Money::format($ligne->sale_price, false) }}</td>
                        <td class="d">{{ Money::format($ligne->line_total, false) }}</td>
                    </tr>
                @endforeach
            </table>

            <hr class="sep">

            @if (! $derniere)
                <table>
                    <tr class="gras"><td>{{ __('Sous-total à reporter') }}</td><td class="d">{{ Money::format($cumul) }}</td></tr>
                </table>
                <div class="suite">{{ __('Suite page :n', ['n' => $p + 2]) }} &gt;&gt;</div>
            @else
                <table>
                    <tr class="total"><td>{{ __('TOTAL') }}</td><td class="d">{{ Money::format($vente->total_sale) }}</td></tr>
                    @if ($vente->total_vat > 0)
                        <tr class="petit"><td>{{ __('dont TVA') }}</td><td class="d">{{ Money::format($vente->total_vat) }}</td></tr>
                    @endif
                </table>

                @if ($vente->amount_tendered !== null)
                    <table style="margin-top: 1.5mm">
                        <tr><td>{{ __('Espèces remises') }}</td><td class="d">{{ Money::format($vente->amount_tendered) }}</td></tr>
                        <tr class="gras"><td>{{ __('Monnaie rendue') }}</td><td class="d">{{ Money::format($vente->change_given) }}</td></tr>
                    </table>
                @endif

                <hr class="sep">

                <div class="centre petit">
                    {{ trans_choice(':n article|:n articles', (int) ceil($nbArticles), ['n' => $qte($nbArticles)]) }}<br>
                    <span class="gras">{{ __('Merci de votre visite !') }}</span>
                </div>
            @endif
        </div>
    @endforeach

    @if (! $pdf && $imprimer)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
