/* Adaptateur d'interface : validations et autorisations restent sur les routes Core. */
(() => {
    let active = null;
    let opener = null;
    let initial = '';
    let pending = false;
    const state = dialog => JSON.stringify([...dialog.querySelectorAll('input, textarea, select')].map(el => [el.name, el.value, el.checked]));
    const dirty = () => active && state(active) !== initial;
    const close = () => {
        if (pending || (dirty() && !window.confirm('Abandonner les modifications non enregistrées ?'))) return;
        active.close();
        active = null;
        opener?.focus();
    };
    document.addEventListener('click', event => {
        const open = event.target.closest('[data-open]');
        if (open) {
            opener = open;
            active = document.getElementById(open.dataset.open);
            if (open.dataset.type) active.querySelector('[name="type"]').value = open.dataset.type;
            initial = state(active);
            active.showModal();
            if (open.dataset.focus) active.querySelector(`#${open.dataset.focus}`)?.focus();
        }
        if (event.target.closest('[data-close]')) close();
    });
    document.addEventListener('cancel', event => { if (event.target === active) { event.preventDefault(); close(); } }, true);
    window.addEventListener('beforeunload', event => { if (pending || dirty()) { event.preventDefault(); event.returnValue = ''; } });
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('[data-async]')) return;
        event.preventDefault();
        if (pending || (form.dataset.confirm && !window.confirm(form.dataset.confirm))) return;
        const errors = form.querySelector('.espace-erreurs');
        const button = event.submitter;
        const label = button.textContent;
        const data = new FormData(form);
        // Les formulaires frères (expériences) ne doivent pas perdre un brouillon.
        if (active.id === 'edition-experiences' && dirty() && !window.confirm('Enregistrer ce formulaire et fermer le panneau ? Les autres modifications non enregistrées seront abandonnées.')) return;
        errors.textContent = '';
        pending = true;
        form.setAttribute('aria-busy', 'true');
        const controls = [...active.querySelectorAll('button, input, textarea, select')];
        const disabled = controls.map(el => el.disabled);
        controls.forEach(el => { el.disabled = true; });
        button.textContent = 'Enregistrement…';
        let saved = false;
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.enregistre) {
                const message = result.errors ? Object.values(result.errors).flat().join('\n') : ({401: 'Votre session a expiré. Reconnectez-vous avant de réessayer.', 403: 'Cette action n’est pas autorisée pour votre compte.', 419: 'La session du formulaire a expiré. Rechargez la page après avoir copié votre saisie.', 429: 'Trop de tentatives. Patientez avant de réessayer.'}[response.status] || 'Enregistrement impossible. Votre saisie est conservée.');
                throw new Error(message);
            }
            saved = true;
            const fresh = await fetch(window.location.pathname, { credentials: 'same-origin', headers: { Accept: 'text/html' }, cache: 'no-store' });
            const doc = new DOMParser().parseFromString(await fresh.text(), 'text/html');
            const root = doc.getElementById('espace-racine');
            if (!fresh.ok || !root) throw new Error('Enregistrement effectué. Rechargez la page pour afficher les données actualisées.');
            const target = opener?.dataset.focus;
            const dialog = opener?.dataset.open;
            active.close();
            active = null;
            document.getElementById('espace-racine').replaceWith(root);
            document.getElementById('espace-status').textContent = 'Modifications enregistrées.';
            const restored = [...document.querySelectorAll('[data-open]')].find(el => el.dataset.open === dialog && el.dataset.focus === target);
            restored?.focus({ preventScroll: true });
        } catch (error) {
            errors.textContent = saved ? 'Enregistrement effectué. Rechargez la page pour afficher les données actualisées.' : error.message;
            errors.focus();
            if (saved) initial = state(active);
        } finally {
            pending = false;
            form.removeAttribute('aria-busy');
            controls.forEach((el, i) => { el.disabled = disabled[i]; });
            button.textContent = label;
            // Une réponse positive ne doit jamais être rejouée après un échec de rafraîchissement.
            if (saved && active) button.disabled = true;
        }
    });
})();
