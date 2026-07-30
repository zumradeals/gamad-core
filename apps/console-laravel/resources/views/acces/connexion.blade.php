<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Connexion</title>
<style>
  :root { color-scheme: light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ko:#f85149; --accent:#58a6ff; }
  @media (prefers-color-scheme: light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  * { box-sizing:border-box; }
  body{ margin:0; min-height:100vh; display:grid; place-items:center; background:var(--bg); color:var(--fg);
        font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; padding:24px; }
  .carte{ background:var(--card); border:1px solid var(--line); border-radius:14px; padding:30px; width:100%; max-width:420px; }
  h1{ font-size:19px; margin:0 0 4px; }
  .sub{ color:var(--muted); font-size:13.5px; margin:0 0 24px; }
  label{ display:block; font-size:13px; color:var(--muted); margin:0 0 6px; }
  input{ width:100%; padding:10px 12px; margin-bottom:16px; border-radius:9px; border:1px solid var(--line);
         background:var(--bg); color:var(--fg); font:inherit; }
  input:focus{ outline:2px solid var(--accent); outline-offset:1px; }
  button{ width:100%; padding:11px; border-radius:9px; border:0; background:var(--accent); color:#05070c;
          font:inherit; font-weight:600; cursor:pointer; }
  button.secondaire{ margin-top:10px; background:transparent; color:var(--accent); border:1px solid var(--accent); }
  button:disabled{ opacity:.55; cursor:wait; }
  .erreur{ color:var(--ko); font-size:13.5px; margin:0 0 16px; }
  .note{ color:var(--muted); font-size:12.5px; margin:22px 0 0; border-top:1px solid var(--line); padding-top:14px; }
  code{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12px; }
</style>
</head>
<body>
<form class="carte" method="POST" action="{{ route('acces.connecter') }}">
  @csrf
  <h1>GAMAD Core</h1>
  <p class="sub">Console du Registre des normes · accès réservé</p>

  @if ($errors->any())
    <p class="erreur">{{ $errors->first() }}</p>
  @endif

  <label for="entite">Référence d'entité</label>
  <input id="entite" name="entite" value="{{ old('entite') }}" autocomplete="username"
         placeholder="AUT-GAMAD-001" required autofocus>

  <label for="secret">Secret</label>
  <input id="secret" name="secret" type="password" autocomplete="current-password" required>

  <button type="submit">Ouvrir une session</button>
  <button class="secondaire" id="passkey" type="button">Utiliser une passkey</button>
  <p class="erreur" id="passkey-erreur" hidden></p>

  <p class="note">
    Une session établit <strong>qui vous êtes</strong>, non ce que vous pouvez faire :
    les droits sont évalués par <code>CAP-CORE-004</code>. Aucun secret n'est conservé,
    seulement son empreinte non réversible (<code>INV-24</code>).
  </p>
</form>
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
    erreur.textContent = entite ? 'Ce navigateur ne prend pas en charge les passkeys.' : 'Saisissez votre référence d’entité.';
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
