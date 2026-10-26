<?php

namespace App\Http\Controllers;

use App\Enums\ExitType;
use App\Models\StockExit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Ticket de caisse d'une vente : page à imprimer (imprimante thermique
 * 80 mm via le navigateur) ou PDF au même format.
 */
class ReceiptController extends Controller
{
    /** Largeur du rouleau, en millimètres. */
    private const LARGEUR_MM = 80;

    public function show(Request $request, StockExit $exit): View
    {
        return view('receipts.ticket', $this->donnees($exit) + [
            'pdf'      => false,
            'imprimer' => $request->boolean('imprimer', true),
        ]);
    }

    public function pdf(StockExit $exit): Response
    {
        $donnees = $this->donnees($exit);

        // dompdf n'accepte qu'un format par document : chaque page prend
        // la hauteur de la plus longue (en-tête, articles, pied). Chaque
        // article prend deux lignes (nom, puis quantité × prix).
        $hauteurMm = $donnees['pages']->map(function ($lignes, $i) use ($donnees, $exit) {
            $derniere = $i === $donnees['pages']->count() - 1;

            return ($i === 0 ? 64 : 34)
                + $lignes->count() * 12
                + $lignes->sum(fn ($l) => intdiv(mb_strlen((string) $l->product?->name), 34)) * 3.5
                + ($derniere ? 44 + ($exit->amount_tendered !== null ? 10 : 0) : 26);
        })->max();

        $points = fn (float $mm) => $mm * 72 / 25.4;

        return Pdf::loadView('receipts.ticket', $donnees + [
            'pdf'       => true,
            'imprimer'  => false,
            'hauteurMm' => $hauteurMm,
        ])
            ->setPaper([0, 0, $points(self::LARGEUR_MM), $points($hauteurMm)])
            ->stream("ticket-{$exit->reference}.pdf");
    }

    /** @return array<string, mixed> */
    private function donnees(StockExit $exit): array
    {
        abort_unless($exit->type === ExitType::Vente, 404);
        Gate::authorize('view', $exit);

        $exit->loadMissing(['lines.product.unit', 'user']);

        // Image embarquée : dompdf n'a pas à aller chercher d'URL.
        $logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/logo-ticket.png')));

        // Ticket long : pages de N articles, le total sur la dernière.
        $pages = $exit->lines->chunk(max(1, (int) config('naneu.pos.ticket_lines_per_page', 8)))->values();
        if ($pages->isEmpty()) {
            $pages = collect([collect()]);
        }

        return ['vente' => $exit, 'logo' => $logo, 'pages' => $pages, 'hauteurMm' => null];
    }
}
