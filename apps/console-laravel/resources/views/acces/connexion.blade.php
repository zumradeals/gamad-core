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

  <p class="note">
    Une session établit <strong>qui vous êtes</strong>, non ce que vous pouvez faire :
    les droits relèvent de <code>CAP-CORE-004</code>, à établir. Aucun secret n'est
    conservé, seulement son empreinte non réversible (<code>INV-24</code>).
  </p>
</form>
</body>
</html>
