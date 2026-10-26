<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationAudit;
use App\Listeners\RecordStockMovementAudit;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\LooseSearch;
use App\Support\ShopSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDatabase();
        $this->configureModels();
        LooseSearch::register();
        $this->configurePasswords();
        $this->configureSecurity();
        $this->registerSubscribers();
        $this->registerGates();
        $this->applyShopSettings();
    }

    /**
     * Les Paramètres de la boutique priment sur le .env. Un worker de file
     * d'attente vit longtemps : il les relit avant chaque tâche.
     */
    private function applyShopSettings(): void
    {
        ShopSettings::apply();

        Queue::before(fn () => ShopSettings::apply());
    }

    /**
     * SQLite est parfaitement adapté à une boutique : un seul fichier, pas
     * de serveur à administrer. Encore faut-il le régler. Sans WAL, le
     * moindre écrivain bloque tous les lecteurs — inacceptable quand la
     * caisse, le planificateur et la file d'attente travaillent ensemble.
     */
    private function configureDatabase(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA journal_mode = WAL');      // lecteurs et écrivain simultanés
        DB::statement('PRAGMA foreign_keys = ON');       // intégrité référentielle réellement appliquée
        DB::statement('PRAGMA busy_timeout = 5000');     // attendre 5 s plutôt que d'échouer sur « database is locked »
        DB::statement('PRAGMA synchronous = NORMAL');    // bon compromis durabilité / vitesse en WAL
    }

    private function configureModels(): void
    {
        // Une propriété absente doit exploser en développement plutôt que
        // de renvoyer silencieusement null dans une facture.
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
    }

    private function configurePasswords(): void
    {
        Password::defaults(function () {
            return Password::min(10)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->uncompromised();   // rejette les mots de passe apparus dans des fuites connues
        });
    }

    private function configureSecurity(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    private function registerSubscribers(): void
    {
        // RecordAuthenticationAudit et RecordStockMovementAudit sont trouvés
        // par la découverte automatique des listeners de Laravel : les
        // inscrire ici en plus doublait chaque ligne du journal d'audit.

        // Activité de la gestion de stock notifiée à la gérance.
        foreach ([\App\Models\Product::class, \App\Models\Category::class, \App\Models\Supplier::class] as $modele) {
            $modele::observe(\App\Observers\StaffActivityObserver::class);
        }
    }

    private function registerGates(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        // Portes transverses, utilisées dans les vues et les composants.
        Gate::define('gerer-parametres', fn (User $user) => $user->canAccess('parametres'));
        Gate::define('voir-journal-audit', fn (User $user) => $user->canAccess('journal'));
    }
}
