/**
 * Pont temps réel entre Reverb et l'interface.
 *
 * On ne s'abonne qu'une fois la page chargée et seulement si l'utilisateur
 * est authentifié : les canaux sont privés, une souscription anonyme serait
 * refusée par l'autorisation de routes/channels.php.
 */

const toast = (detail) => {
    window.dispatchEvent(new CustomEvent('toast', { detail }));
};

const abonner = () => {
    if (!window.Echo || !document.querySelector('meta[name="csrf-token"]')) {
        return;
    }

    // --- Alertes de stock -------------------------------------------------
    window.Echo.private('naneu.alertes')
        .listen('.alerte.ouverte', (e) => {
            toast({
                type: e.gravite === 'critique' ? 'erreur' : 'alerte',
                titre: e.type_label,
                message: e.message,
                duree: e.gravite === 'critique' ? 12000 : 7000,
            });

            // La cloche et les compteurs d'alertes se rafraîchissent d'eux-mêmes.
            window.Livewire?.dispatch('alerte-recue', { alerte: e });
        })
        .listen('.alerte.resolue', (e) => {
            window.Livewire?.dispatch('alerte-resolue', { alerte: e });
        })
        .error((erreur) => {
            // Un magasinier sans droit sur ce canal reçoit un 403 : ce n'est
            // pas une panne, inutile de polluer la console.
            if (erreur?.status !== 403) {
                console.warn('Reverb — canal alertes indisponible', erreur);
            }
        });

    // --- Mouvements de stock ----------------------------------------------
    window.Echo.private('naneu.stock')
        .listen('.stock.modifie', (e) => {
            window.Livewire?.dispatch('stock-modifie', { produit: e });
        })
        .listen('.entree.enregistree', (e) => {
            window.Livewire?.dispatch('entree-enregistree', { entree: e });
        })
        .listen('.sortie.enregistree', (e) => {
            window.Livewire?.dispatch('sortie-enregistree', { sortie: e });
        });

    // --- Notifications personnelles ---------------------------------------
    const idUtilisateur = document.querySelector('meta[name="utilisateur-id"]')?.content;

    if (idUtilisateur) {
        window.Echo.private(`App.Models.User.${idUtilisateur}`)
            .notification(() => {
                window.Livewire?.dispatch('notification-recue');
            });
    }
};

document.addEventListener('DOMContentLoaded', abonner);
