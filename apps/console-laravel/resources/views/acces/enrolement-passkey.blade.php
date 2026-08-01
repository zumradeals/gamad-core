<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8d40a">
    <title>Enrôler une passkey — GAMAD Core</title>
    <link rel="stylesheet" href="{{ asset('css/gamad-core.css') }}">
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-story" aria-label="Sécurité de GAMAD Core">
        <img class="auth-logo"
             src="{{ asset('images/logo-gamad.jpg') }}"
             alt="Logo GAMAD"
             width="108"
             height="108">
        <div class="auth-story__message">
            <p class="eyebrow" style="color:#111417">Accès protégé</p>
            <h1>Votre clé reste entre vos mains.</h1>
            <p>La passkey renforce l’accès sans transmettre votre clé privée au Core.</p>
        </div>
    </section>

    <section class="auth-form-panel">
        <form class="auth-form" onsubmit="return false">
            <p class="eyebrow">Enrôlement sécurisé</p>
            <h2>Créer une passkey</h2>
            <p class="auth-form__intro">Associez cet appareil ou votre clé physique à votre identité GAMAD.</p>

            <div class="field">
                <label for="entite">Référence d’identité</label>
                <input class="input"
                       id="entite"
                       autocomplete="username"
                       placeholder="AUT-GAMAD-001"
                       value="{{ session('passkey_entite', '') }}"
                       required
                       @if(! session('passkey_entite')) autofocus @endif>
            </div>

            <div class="field">
                <label for="jeton">Code d’enrôlement à usage unique</label>
                {{-- Rempli quand l'autorisation vient d'être accordée depuis
                     « Mon accès » : le jeton ne transite pas par l'écran, il ne
                     survit pas au rechargement. --}}
                <input class="input"
                       id="jeton"
                       type="password"
                       autocomplete="one-time-code"
                       value="{{ session('passkey_jeton', '') }}"
                       required>
            </div>

            <div class="field">
                <label for="libelle">Nom de cette passkey</label>
                <input class="input"
                       id="libelle"
                       maxlength="120"
                       placeholder="Téléphone Android ou clé principale"
                       required>
            </div>

            <button class="button button--primary button--full" id="enroler" type="button">
                Créer la passkey
            </button>
            <p class="auth-error-text" id="erreur" role="alert" hidden></p>

            <p class="auth-note">
                Le Core ne reçoit jamais votre clé privée ni vos données biométriques.
                Le code à usage unique est fourni par l’autorité GAMAD.
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
  const octets = new Uint8Array(valeur); let binaire = '';
  octets.forEach(octet => binaire += String.fromCharCode(octet));
  return btoa(binaire).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};
const attestationJson = (credential) => ({
  id: credential.id,
  type: credential.type,
  rawId: versBase64url(credential.rawId),
  response: {
    clientDataJSON: versBase64url(credential.response.clientDataJSON),
    attestationObject: versBase64url(credential.response.attestationObject),
    transports: credential.response.getTransports ? credential.response.getTransports() : [],
  },
  clientExtensionResults: credential.getClientExtensionResults(),
});
document.getElementById('enroler').addEventListener('click', async () => {
  const bouton = document.getElementById('enroler'); const erreur = document.getElementById('erreur');
  erreur.hidden = true; bouton.disabled = true;
  try {
    if (!window.PublicKeyCredential) throw new Error('Ce navigateur ne prend pas en charge les passkeys.');
    const entite = document.getElementById('entite').value.trim();
    const jeton = document.getElementById('jeton').value;
    const libelle = document.getElementById('libelle').value.trim();
    const debut = await fetch(@json(route('passkeys.enrolement.options')), {
      method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
      body:JSON.stringify({entite,jeton}),
    });
    const ceremonie = await debut.json();
    if (!debut.ok) throw new Error(ceremonie.message || 'Enrôlement refusé.');
    const options = ceremonie.options;
    options.challenge = versOctets(options.challenge);
    options.user.id = versOctets(options.user.id);
    options.excludeCredentials = (options.excludeCredentials || []).map(item => ({...item,id:versOctets(item.id)}));
    const credential = await navigator.credentials.create({publicKey:options});
    const fin = await fetch(@json(route('passkeys.enroler')), {
      method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
      body:JSON.stringify({ceremonie:ceremonie.ceremonie,libelle,credential:attestationJson(credential)}),
    });
    const resultat = await fin.json();
    if (!fin.ok) throw new Error(resultat.message || 'Enrôlement refusé.');
    window.location.assign(resultat.redirection || '/connexion');
  } catch (e) {
    erreur.textContent = e.name === 'NotAllowedError' ? 'La création a été annulée ou refusée.' : (e.message || 'Enrôlement refusé.');
    erreur.hidden = false;
  } finally { bouton.disabled = false; }
});
</script>
</body>
</html>
