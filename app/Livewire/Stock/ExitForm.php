<?php

namespace App\Livewire\Stock;

use App\Data\ExitLineData;
use App\Enums\ExitType;
use App\Enums\MovementReason;
use App\Enums\UserRole;
use App\Exceptions\InsufficientStockException;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockExit;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Nouvelle sortie')]
class ExitForm extends Component
{
    use InteractsWithToasts;

    public string $reference = '';
    public string $type = 'vente';
    public string $exit_date = '';
    public string $note = '';

    /** @var array<int, array<string, mixed>> */
    public array $lignes = [];

    public string $recherche = '';

    public function mount(): void
    {
        $this->authorize('create', StockExit::class);

        $this->reference = StockExit::nextReference();
        $this->exit_date = now()->toDateString();
    }

    public function updatedType(string $valeur): void
    {
        $type = ExitType::tryFrom($valeur);

        if ($type && ! auth()->user()->can('createOfType', [StockExit::class, $type])) {
            $this->type = ExitType::Vente->value;

            $this->toastErreur('Votre profil ne permet pas ce type de sortie.');
        }
    }

    // -----------------------------------------------------------------
    // Lignes
    // -----------------------------------------------------------------

    public function ajouterProduit(int $productId): void
    {
        $produit = Product::with('unit')->find($productId);

        if (! $produit) {
            $this->toastErreur('Produit introuvable.');

            return;
        }

        foreach ($this->lignes as $index => $ligne) {
            if ((int) $ligne['product_id'] === $produit->id) {
                $this->lignes[$index]['quantity'] = (float) $ligne['quantity'] + 1;
                $this->recherche = '';

                return;
            }
        }

        $dispo = $this->stock()->availability($produit, $this->motif(), $this->dateSortie());

        if ($dispo['utilisable'] <= 1e-6) {
            if ($dispo['perime'] > 1e-6) {
                $this->stock()->signalBlockedExpiredSale($produit, auth()->user());
                $this->toastErreur(__('« :nom » : tout le stock restant est périmé. Vente bloquée — retirez-le du rayon.', ['nom' => $produit->name]));
            } elseif ($dispo['pas_encore_recu'] > 1e-6) {
                $this->toastErreur(__('« :nom » n\'avait pas encore été reçu à la date de cette sortie.', ['nom' => $produit->name]));
            } else {
                $this->toastErreur(__('« :nom » est en rupture de stock.', ['nom' => $produit->name]));
            }

            return;
        }

        $this->lignes[] = [
            'product_id' => $produit->id,
            'nom'        => $produit->name,
            'sku'        => $produit->sku,
            'unite'      => $produit->unit?->symbol ?? '',
            'stock'      => $dispo['utilisable'],
            'tva'        => $produit->effectiveVatRate(),
            'quantity'   => 1,
            'sale_price' => (string) $produit->sale_price,
            'catalogue'  => $produit->sale_price,
        ];

        $this->recherche = '';
    }

    public function scanner(): void
    {
        $terme = trim($this->recherche);

        if ($terme === '') {
            return;
        }

        $produit = Product::query()
            ->active()
            ->where(fn ($q) => $q->where('barcode', $terme)->orWhere('internal_code', $terme))
            ->first();

        if ($produit) {
            $this->ajouterProduit($produit->id);

            return;
        }

        $correspondances = Product::query()->active()->search($terme)->limit(2)->get();

        if ($correspondances->count() === 1) {
            $this->ajouterProduit($correspondances->first()->id);
        }
    }

    public function retirerLigne(int $index): void
    {
        unset($this->lignes[$index]);

        $this->lignes = array_values($this->lignes);
    }

    // -----------------------------------------------------------------
    // Aperçu FIFO
    // -----------------------------------------------------------------

    /**
     * Simule la consommation sans rien écrire : montre à l'opérateur quels
     * lots vont être vidés et à quel coût. C'est ce qui rend le FIFO lisible
     * plutôt que magique — et ça permet de repérer avant validation qu'on
     * s'apprête à écouler un lot proche de la péremption.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getApercuProperty(): array
    {
        $apercu = [];

        foreach ($this->lignes as $index => $ligne) {
            $demande = (float) str_replace(',', '.', (string) $ligne['quantity']);

            if ($demande <= 0) {
                continue;
            }

            $produit = Product::find($ligne['product_id']);

            if (! $produit) {
                continue;
            }

            // Même file que l'enregistrement : chronologie et péremption
            // comprises, l'aperçu ne promet jamais ce qui sera refusé.
            $lots = $this->stock()->consumptionQueue($produit, $this->motif(), $this->dateSortie())->get();
            $dispo = $this->stock()->availability($produit, $this->motif(), $this->dateSortie());

            $restant = $demande;
            $prelevements = [];
            $cout = 0;

            foreach ($lots as $lot) {
                if ($restant <= 1e-6) {
                    break;
                }

                $pris = min((float) $lot->quantity_remaining, $restant);
                $coutLigne = (int) round($pris * $lot->purchase_price);

                $prelevements[] = [
                    'lot'       => $lot->batch_number,
                    'quantite'  => $pris,
                    'prix'      => $lot->purchase_price,
                    'cout'      => $coutLigne,
                    'peremption' => $lot->expiry_date?->format('d/m/Y'),
                    'jours'     => $lot->daysUntilExpiry(),
                    'vide'      => abs($pris - (float) $lot->quantity_remaining) < 1e-6,
                ];

                $cout += $coutLigne;
                $restant -= $pris;
            }

            $apercu[$index] = [
                'prelevements'    => $prelevements,
                'cout'            => $cout,
                'manquant'        => max(0, round($restant, 3)),
                'perime'          => $dispo['perime'],
                'pas_encore_recu' => $dispo['pas_encore_recu'],
            ];
        }

        return $apercu;
    }

    public function getTotalVenteProperty(): int
    {
        if (! ExitType::from($this->type)->generatesRevenue()) {
            return 0;
        }

        return collect($this->lignes)->sum(function (array $ligne) {
            $quantite = (float) str_replace(',', '.', (string) $ligne['quantity']);

            return (int) round($quantite * Money::parse($ligne['sale_price']));
        });
    }

    /** TVA contenue dans le total TTC, ligne par ligne. */
    public function getTotalTvaProperty(): int
    {
        if (! ExitType::from($this->type)->generatesRevenue()) {
            return 0;
        }

        return collect($this->lignes)->sum(function (array $ligne) {
            $quantite = (float) str_replace(',', '.', (string) $ligne['quantity']);
            $ttc = (int) round($quantite * Money::parse($ligne['sale_price']));

            return Money::vatFromInclusive($ttc, (float) ($ligne['tva'] ?? 0));
        });
    }

    public function getTotalCoutProperty(): int
    {
        return collect($this->apercu)->sum('cout');
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    private function motif(): MovementReason
    {
        return (ExitType::tryFrom($this->type) ?? ExitType::Vente)->toReason();
    }

    private function dateSortie(): Carbon
    {
        return Carbon::parse($this->exit_date ?: now()->toDateString());
    }

    // -----------------------------------------------------------------
    // Enregistrement
    // -----------------------------------------------------------------

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'type'      => ['required', Rule::enum(ExitType::class)],
            'exit_date' => ['required', 'date', 'before_or_equal:today'],
            'note'      => ['nullable', 'string', 'max:1000'],

            'lignes'              => ['required', 'array', 'min:1'],
            'lignes.*.product_id' => ['required', 'exists:products,id'],
            'lignes.*.quantity'   => ['required', 'numeric', 'gt:0'],
            'lignes.*.sale_price' => ['nullable'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'lignes.required'       => 'Ajoutez au moins un produit.',
            'lignes.min'            => 'Ajoutez au moins un produit.',
            'lignes.*.quantity.gt'  => 'La quantité doit être supérieure à zéro.',
        ];
    }

    public function enregistrer(StockService $stock)
    {
        $type = ExitType::from($this->type);

        $this->authorize('createOfType', [StockExit::class, $type]);

        $this->validate();

        // Dernier contrôle avant écriture : l'aperçu a pu être calculé il y
        // a une minute, une autre caisse a pu vider le lot entre-temps.
        foreach ($this->apercu as $index => $detail) {
            if ($detail['manquant'] > 0) {
                $qte = fn (float $q) => rtrim(rtrim(Money::number($q, 3), '0'), ',.');
                $message = __('Stock insuffisant : il manque :quantite.', ['quantite' => $qte($detail['manquant'])]);

                if ($detail['perime'] > 1e-6) {
                    $message .= ' '.__(':qte périmé(s) en stock : vente bloquée, retirez-les du rayon.', ['qte' => $qte($detail['perime'])]);
                    $this->stock()->signalBlockedExpiredSale(Product::find($this->lignes[$index]['product_id']), auth()->user());
                }

                if ($detail['pas_encore_recu'] > 1e-6) {
                    $message .= ' '.__(':qte reçu(s) après la date de la sortie.', ['qte' => $qte($detail['pas_encore_recu'])]);
                }

                $this->addError("lignes.{$index}.quantity", $message);
            }
        }

        $remises = $type->generatesRevenue() ? $this->controlerPrix() : [];

        if ($this->getErrorBag()->isNotEmpty()) {
            return null;
        }

        $lignes = collect($this->lignes)
            ->map(fn (array $ligne) => ExitLineData::fromArray([
                'product_id' => $ligne['product_id'],
                'quantity'   => $ligne['quantity'],
                'sale_price' => $type->generatesRevenue() ? $ligne['sale_price'] : null,
            ]))
            ->all();

        try {
            $sortie = $stock->issue(auth()->user(), $type, $lignes, [
                'exit_date' => $this->exit_date,
                'note'      => $this->note ?: null,
            ]);
        } catch (InsufficientStockException $e) {
            $this->toastErreur($e->getMessage());

            return null;
        } catch (\Throwable $e) {
            report($e);

            $this->toastErreur(__("La sortie n'a pas pu être enregistrée : :erreur", ['erreur' => $e->getMessage()]));

            return null;
        }

        if ($remises !== []) {
            AuditLog::record('vente.prix_modifie', auth()->user(), $sortie, [
                'reference' => $sortie->reference,
                'motif'     => $this->note,
                'lignes'    => $remises,
            ]);
        }

        session()->flash('succes', __('Sortie :reference enregistrée.', ['reference' => $sortie->reference]));

        return $this->redirectRoute('sorties.show', ['exit' => $sortie], navigate: true);
    }

    /**
     * Un prix de vente saisi à la main s'écarte du catalogue : il doit être
     * positif, motivé dans la note, et une remise au-delà du plafond est
     * réservée à la gérance. Renvoie les écarts à tracer au journal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function controlerPrix(): array
    {
        $plafond = (float) config('naneu.sales.max_discount_percent');
        $gerance = auth()->user()->hasAtLeast(UserRole::Gerant);
        $ecarts = [];

        foreach ($this->lignes as $index => $ligne) {
            $saisi = trim((string) $ligne['sale_price']);
            $prix = Money::parse($saisi);

            if (! preg_match('/^\d[\d\s.,]*$/u', $saisi) || $prix <= 0) {
                $this->addError("lignes.{$index}.sale_price", __('Prix de vente invalide : saisissez un montant supérieur à zéro.'));

                continue;
            }

            $catalogue = (int) ($ligne['catalogue'] ?? Product::find($ligne['product_id'])?->sale_price);

            if ($prix === $catalogue) {
                continue;
            }

            $remise = $catalogue > 0 ? round(($catalogue - $prix) / $catalogue * 100, 1) : 0.0;

            if (! $gerance && $remise > $plafond) {
                $this->addError("lignes.{$index}.sale_price", __('Remise de :remise % : au-delà de :plafond %, seule la gérance peut l\'accorder.', [
                    'remise' => Money::number($remise, 1), 'plafond' => Money::number($plafond, 0),
                ]));
            }

            $ecarts[] = [
                'produit'   => $ligne['nom'],
                'catalogue' => $catalogue,
                'applique'  => $prix,
                'remise'    => $remise,
            ];
        }

        if ($ecarts !== [] && blank($this->note)) {
            $this->addError('note', __('Prix modifié par rapport au catalogue : indiquez le motif dans la note.'));
        }

        return $ecarts;
    }

    /** @return Collection<int, Product> */
    public function getResultatsProperty(): Collection
    {
        if (mb_strlen(trim($this->recherche)) < 2) {
            return collect();
        }

        return Product::query()
            ->active()
            ->search($this->recherche)
            ->with('unit:id,symbol')
            ->limit(8)
            ->get();
    }

    public function render(): View
    {
        $typesAutorises = collect(ExitType::cases())
            ->filter(fn (ExitType $type) => auth()->user()->can('createOfType', [StockExit::class, $type]))
            ->mapWithKeys(fn (ExitType $type) => [$type->value => $type->label()])
            ->all();

        return view('livewire.stock.exit-form', [
            'typesAutorises' => $typesAutorises,
            'resultats'      => $this->resultats,
            'apercu'         => $this->apercu,
            'totalVente'     => $this->totalVente,
            'totalTva'       => $this->totalTva,
            'totalCout'      => $this->totalCout,
            'estVente'       => ExitType::from($this->type)->generatesRevenue(),
            // Chaque tuile affiche le stock réellement vendable à la date de
            // la sortie (hors lots périmés ou pas encore reçus).
            'vignettes'      => Product::query()->active()->with('unit:id,symbol')->quickItems()->limit(18)->get()
                ->each(function (Product $p) {
                    $dispo = $this->stock()->availability($p, $this->motif(), $this->dateSortie());
                    $p->setAttribute('vendable', $dispo['utilisable']);
                    $p->setAttribute('perime', $dispo['perime']);
                }),
        ]);
    }
}
