<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Enrôler une passkey</title>
<style>
  :root { color-scheme:light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ko:#f85149; --accent:#58a6ff; }
  @media (prefers-color-scheme:light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  *{box-sizing:border-box} body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--fg);font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:24px}
  .carte{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:30px;width:100%;max-width:470px}
  h1{font-size:19px;margin:0 0 4px}.sub,.note{color:var(--muted);font-size:13px}.sub{margin:0 0 24px}
  label{display:block;font-size:13px;color:var(--muted);margin:0 0 6px}input{width:100%;padding:10px 12px;margin-bottom:16px;border-radius:9px;border:1px solid var(--line);background:var(--bg);color:var(--fg);font:inherit}
  button{width:100%;padding:11px;border-radius:9px;border:0;background:var(--accent);color:#05070c;font:inherit;font-weight:600;cursor:pointer}button:disabled{opacity:.55;cursor:wait}
  .erreur{color:var(--ko);font-size:13.5px}.note{border-top:1px solid var(--line);padding-top:14px;margin-top:20px}
</style>
</head>
<body>
<main class="carte">
  <h1>Enrôler une passkey</h1>
  <p class="sub">Facteur fort A2 · clé privée conservée par votre authenticator</p>
  <label for="entite">Référence d’entité</label>
  <input id="entite" autocomplete="username" placeholder="AUT-GAMAD-001" required>
  <label for="jeton">Jeton d’enrôlement à usage unique</label>
  <input id="jeton" type="password" autocomplete="one-time-code" required>
  <label for="libelle">Nom de cette passkey</label>
  <input id="libelle" maxlength="120" placeholder="Clé principale" required>
  <button id="enroler" type="button">Créer la passkey</button>
  <p class="erreur" id="erreur" hidden></p>
  <p class="note">Le jeton est préparé localement par l’autorité avec <code>php artisan identite:preparer-passkey</code>. Le Core ne reçoit jamais votre clé privée ni vos données biométriques.</p>
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
