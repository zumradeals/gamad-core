<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8d40a">
    <title>Connexion — GAMAD Core</title>
    <link rel="stylesheet" href="{{ asset('css/gamad-core.css') }}">
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-story" aria-label="Présentation de GAMAD Core">
        <img class="auth-logo"
             src="{{ asset('images/logo-gamad.jpg') }}"
             alt="Logo GAMAD"
             width="108"
             height="108">
        <div class="auth-story__message">
            <p class="eyebrow" style="color:#111417">Noyau souverain</p>
            <h1>La cohérence au service de la mission.</h1>
            <p>Formation · Travail · Adoration</p>
        </div>
    </section>

    <section class="auth-form-panel">
        <form class="auth-form" method="POST" action="{{ route('acces.connecter') }}">
            @csrf
            <p class="eyebrow">Accès réservé</p>
            <h2>Ouvrir la console</h2>
            <p class="auth-form__intro">Identifiez-vous pour accéder aux opérations autorisées du Core.</p>

            @if ($errors->any())
                <div class="form-error auth-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <div class="field">
                <label for="entite">Référence d’identité</label>
                <input class="input"
                       id="entite"
                       name="entite"
                       value="{{ old('entite') }}"
                       autocomplete="username"
                       placeholder="AUT-GAMAD-001"
                       required
                       autofocus>
            </div>

            <div class="field">
                <label for="secret">Secret</label>
                <input class="input"
                       id="secret"
                       name="secret"
                       type="password"
                       autocomplete="current-password"
                       required>
            </div>

            <button class="button button--primary button--full" type="submit">Ouvrir une session</button>

            <div class="auth-divider">ou</div>

            <button class="button button--secondary button--full" id="passkey" type="button">
                Utiliser une passkey
            </button>
            <p class="auth-error-text" id="passkey-erreur" hidden></p>

            <p class="auth-note">
                Une session établit votre identité. Chaque droit est ensuite évalué par
                <code>CAP-CORE-004</code>. Aucun secret n’est conservé en clair.
            </p>
        </form>
    </section>
</main>

<script>
const csrf = @json(csrf_token());
const versOctets = (valeur) => {
  const normalisee = valeur.replace(/-/g, '+').replace(/_/g, '/');
  const binaire = atob(normalisee + '='.repeat((4 - normalisee.length % 4) % 4));
  return Uint8Array.from(binaire, caractere => caractere.charCodeAt(0));
};
const versBase64url = (valeur) => {
  const octets = new Uint8Array(valeur);
  let binaire = '';
  octets.forEach(octet => binaire += String.fromCharCode(octet));
  return btoa(binaire).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};
const assertionJson = (credential) => ({
  id: credential.id,
  type: credential.type,
  rawId: versBase64url(credential.rawId),
  response: {
    clientDataJSON: versBase64url(credential.response.clientDataJSON),
    authenticatorData: versBase64url(credential.response.authenticatorData),
    signature: versBase64url(credential.response.signature),
    userHandle: credential.response.userHandle ? versBase64url(credential.response.userHandle) : null,
  },
  clientExtensionResults: credential.getClientExtensionResults(),
});
document.getElementById('passkey').addEventListener('click', async () => {
  const bouton = document.getElementById('passkey');
  const erreur = document.getElementById('passkey-erreur');
  const entite = document.getElementById('entite').value.trim();
  erreur.hidden = true;
  if (!window.PublicKeyCredential || !entite) {
    erreur.textContent = entite ? 'Ce navigateur ne prend pas en charge les passkeys.' : 'Saisissez votre référence d’identité.';
    erreur.hidden = false;
    return;
  }
  bouton.disabled = true;
  try {
    const debut = await fetch(@json(route('passkeys.authentification.options')), {
      method: 'POST',
      headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
      body: JSON.stringify({entite}),
    });
    const ceremonie = await debut.json();
    if (!debut.ok) throw new Error(ceremonie.message || 'Cérémonie indisponible.');
    const options = ceremonie.options;
    options.challenge = versOctets(options.challenge);
    options.allowCredentials = (options.allowCredentials || []).map(item => ({...item, id:versOctets(item.id)}));
    const credential = await navigator.credentials.get({publicKey: options});
    const fin = await fetch(@json(route('passkeys.authentifier')), {
      method: 'POST',
      headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
      body: JSON.stringify({ceremonie: ceremonie.ceremonie, credential: assertionJson(credential)}),
    });
    const resultat = await fin.json();
    if (!fin.ok) throw new Error(resultat.message || 'Passkey refusée.');
    window.location.assign(resultat.redirection || '/');
  } catch (e) {
    erreur.textContent = e.name === 'NotAllowedError'
      ? 'La vérification a été annulée ou refusée.'
      : (e.message || 'Passkey refusée.');
    erreur.hidden = false;
  } finally {
    bouton.disabled = false;
  }
});
</script>
</body>
</html>
