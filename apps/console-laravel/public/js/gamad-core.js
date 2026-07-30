(() => {
    const body = document.body;
    const menuButton = document.querySelector('[data-menu-toggle]');
    const scrim = document.querySelector('[data-menu-close]');

    const fermerMenu = () => {
        body.classList.remove('menu-open');
        menuButton?.setAttribute('aria-expanded', 'false');
    };

    menuButton?.addEventListener('click', () => {
        const ouvert = body.classList.toggle('menu-open');
        menuButton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    });
    scrim?.addEventListener('click', fermerMenu);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') fermerMenu();
    });

    document.querySelectorAll('[data-flash-close]').forEach(button => {
        button.addEventListener('click', () => button.closest('[data-flash]')?.remove());
    });

    const formulaireIdentite = document.querySelector('[data-identity-form]');
    if (!formulaireIdentite) return;

    const libelle = formulaireIdentite.querySelector('[name="libelle"]');
    const classification = formulaireIdentite.querySelector('[name="classification"]');
    const provisoire = formulaireIdentite.querySelector('[name="provisoire"]');
    const recapNom = document.querySelector('[data-summary-name]');
    const recapType = document.querySelector('[data-summary-type]');
    const recapCanal = document.querySelector('[data-summary-channel]');
    const recapClassification = document.querySelector('[data-summary-classification]');
    const recapEtat = document.querySelector('[data-summary-state]');
    const typeLabels = {
        personne: 'Personne',
        organisation: 'Organisation',
        produit: 'Produit',
        realm: 'Realm',
        agent: 'Agent',
        service: 'Service',
    };
    const techniques = ['produit', 'realm', 'agent', 'service'];

    const actualiser = () => {
        const type = formulaireIdentite.querySelector('[name="type"]:checked')?.value || 'personne';
        if (recapNom) recapNom.textContent = libelle?.value.trim() || 'À renseigner';
        if (recapType) recapType.textContent = typeLabels[type] || type;
        if (recapCanal) {
            recapCanal.textContent = techniques.includes(type)
                ? 'Création technique'
                : 'Autorité';
        }
        if (recapClassification) {
            recapClassification.textContent = classification?.selectedOptions[0]?.text || 'Interne';
        }
        if (recapEtat) recapEtat.textContent = provisoire?.checked ? 'Provisoire' : 'Active';
    };

    formulaireIdentite.addEventListener('input', actualiser);
    formulaireIdentite.addEventListener('change', actualiser);
    actualiser();
})();
