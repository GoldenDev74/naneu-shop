{{--
    Monogramme Naneu Shop : un N dont les deux extrémités s'ouvrent en
    feuilles. Dessiné au trait, en currentColor, pour suivre la couleur du
    contexte (text-marque-400, text-white…). Les tracés portent pathLength=1
    pour permettre l'animation de dessin (.logo-trace dans app.css).
--}}
<svg viewBox="-4 -4 108 108" fill="none" stroke="currentColor" stroke-width="5"
     stroke-linecap="round" stroke-linejoin="round" role="img"
     aria-label="{{ config('naneu.shop.name') }}" {{ $attributes }}>
    <path pathLength="1" d="M4 3V60C4 82 20 97 50 97"/>
    <path pathLength="1" d="M4 56C20 62 40 76 50 97"/>
    <path pathLength="1" d="M4 3C14 24 30 36 54 46C80 56 97 62 97 76"/>
    <path pathLength="1" d="M50 3C80 3 97 16 97 38V97"/>
    <path pathLength="1" d="M50 3C56 20 74 32 97 40"/>
</svg>
