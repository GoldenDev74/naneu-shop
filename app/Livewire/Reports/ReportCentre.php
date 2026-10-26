<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Product;
use App\Models\StockBatch;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Rapports')]
class ReportCentre extends Component
{
    use InteractsWithToasts;

    #[Url(except: '')]
    public string $depuis = '';

    #[Url(except: '')]
    public string $jusqua = '';

    /** Jour cliqué sur le graphique : le détail produit se recentre dessus. */
    public ?string $jourChoisi = null;

    public function choisirJour(?string $date): void
    {
        $this->jourChoisi = $this->jourChoisi === $date ? null : $date;
    }

    public function mount(): void
    {
        $this->depuis = $this->depuis ?: now()->startOfMonth()->toDateString();
        $this->jusqua = $this->jusqua ?: now()->toDateString();
    }

    public function periode(string $raccourci): void
    {
        [$this->depuis, $this->jusqua] = match ($raccourci) {
            'jour'      => [now()->toDateString(), now()->toDateString()],
            'semaine'   => [now()->startOfWeek()->toDateString(), now()->toDateString()],
            'mois'      => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'mois_dernier' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'annee'     => [now()->startOfYear()->toDateString(), now()->toDateString()],
            default     => [$this->depuis, $this->jusqua],
        };
    }

    /**
     * Export CSV de l'état du stock. Le CSV est généré en flux plutôt
     * qu'assemblé en mémoire : un catalogue de plusieurs milliers de
     * lignes ne doit pas saturer le serveur.
     */
    public function exporterStock(): StreamedResponse
    {
        $nom = 'stock_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () {
            $sortie = fopen('php://output', 'w');

            // BOM UTF-8 : sans lui, Excel affiche « PÃ©remption » au lieu de « Péremption ».
            fwrite($sortie, "\xEF\xBB\xBF");

            fputcsv($sortie, [
                'Référence', 'Code-barres', 'Produit', 'Catégorie', 'Unité',
                'Stock', 'Seuil', 'Prix de vente', 'Valeur du stock',
                'Péremption la plus proche', 'Statut',
            ], ';');

            Product::query()
                ->with(['category:id,name', 'unit:id,symbol'])
                ->orderBy('name')
                ->chunk(200, function ($produits) use ($sortie) {
                    foreach ($produits as $produit) {
                        fputcsv($sortie, [
                            $produit->sku,
                            $produit->barcode ?? $produit->internal_code ?? '',
                            $produit->name,
                            $produit->category?->name ?? '',
                            $produit->unit?->symbol ?? '',
                            number_format((float) $produit->quantity_on_hand, 3, ',', ''),
                            number_format((float) $produit->alert_quantity, 3, ',', ''),
                            $produit->sale_price,
                            $produit->stockValue(),
                            $produit->nearestExpiry()?->format('d/m/Y') ?? '',
                            match ($produit->stockStatus()) {
                                'rupture' => 'Rupture',
                                'bas'     => 'Stock bas',
                                default   => 'Normal',
                            },
                        ], ';');
                    }
                });

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Export CSV des lots, avec valorisation FIFO lot par lot. */
    public function exporterLots(): StreamedResponse
    {
        $nom = 'lots_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");

            fputcsv($sortie, [
                'Lot', 'Produit', 'Référence', 'Reçu le', 'Péremption',
                'Quantité reçue', 'Quantité restante', 'Prix d\'achat', 'Valeur restante', 'Fournisseur',
            ], ';');

            StockBatch::query()
                ->available()
                ->with(['product:id,name,sku', 'supplier:id,name'])
                ->inConsumptionOrder()
                ->chunk(200, function ($lots) use ($sortie) {
                    foreach ($lots as $lot) {
                        fputcsv($sortie, [
                            $lot->batch_number,
                            $lot->product?->name ?? '',
                            $lot->product?->sku ?? '',
                            $lot->received_at->format('d/m/Y'),
                            $lot->expiry_date?->format('d/m/Y') ?? '',
                            number_format((float) $lot->quantity_received, 3, ',', ''),
                            number_format((float) $lot->quantity_remaining, 3, ',', ''),
                            $lot->purchase_price,
                            $lot->remainingValue(),
                            $lot->supplier?->name ?? '',
                        ], ';');
                    }
                });

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Archive une copie de l'inventaire valorisé sur MinIO. Sert de trace
     * datée, utile pour justifier la valeur du stock a posteriori.
     */
    public function archiverSurMinio(ReportService $reports): void
    {
        try {
            $lignes = ["Inventaire valorisé — ".config('naneu.shop.name'),
                       'Généré le '.now()->format('d/m/Y H:i'),
                       'Valeur totale : '.Money::format($reports->stockValue()),
                       '',
                       'Référence;Produit;Stock;Valeur'];

            Product::query()->active()->orderBy('name')->chunk(200, function ($produits) use (&$lignes) {
                foreach ($produits as $produit) {
                    $lignes[] = implode(';', [
                        $produit->sku,
                        $produit->name,
                        number_format((float) $produit->quantity_on_hand, 3, ',', ''),
                        $produit->stockValue(),
                    ]);
                }
            });

            $chemin = config('naneu.storage.exports_path').'/inventaire_'.now()->format('Y-m-d_His').'.csv';

            Storage::disk(config('naneu.storage.disk'))->put($chemin, "\xEF\xBB\xBF".implode("\n", $lignes));

            $this->toastSucces(__('Inventaire archivé sur MinIO : :chemin', ['chemin' => $chemin]));
        } catch (\Throwable $e) {
            report($e);

            $this->toastErreur(__('Archivage impossible : MinIO est-il démarré ? :erreur', ['erreur' => str($e->getMessage())->limit(120)]));
        }
    }

    public function render(ReportService $reports): View
    {
        return view('livewire.reports.report-centre', [
            'ventes'       => $reports->salesTotal($this->depuis, $this->jusqua),
            'marge'        => $reports->marginTotal($this->depuis, $this->jusqua),
            'tva'          => $reports->vatTotal($this->depuis, $this->jusqua),
            'pertes'       => $reports->shrinkageTotal($this->depuis, $this->jusqua),
            'valeurStock'  => $reports->stockValue(),
            'topProduits'  => $reports->topProducts($this->depuis, $this->jusqua, 10),
            'serie'        => $reports->salesSeries(30),
            'produitsVendus' => $this->jourChoisi
                ? $reports->topProducts($this->jourChoisi, $this->jourChoisi, 15, 'chiffre')
                : $reports->topProducts($this->depuis, $this->jusqua, 15, 'chiffre'),
            'aReappro'     => $reports->reorderList(),
        ]);
    }
}
