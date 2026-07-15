<?php
/** @var bool $adminAuthenticated */
/** @var bool $personAuthenticated */
/** @var string $pageTitle */
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? "GAMAD Core — Console d'Exploitation") ?></title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<?php if ($adminAuthenticated || $personAuthenticated): ?>
<header class="console-header">
    <div class="brand">GAMAD Core<small>Console d'Exploitation — instrument de pilotage interne, pas un produit</small></div>
    <nav class="console-nav">
        <?php if ($adminAuthenticated): ?>
        <a href="/">Tableau de bord</a>
        <a href="/identities">Identités</a>
        <a href="/dead-letters">Dead letters</a>
        <?php endif; ?>
        <?php if ($personAuthenticated): ?>
        <a href="/personnes">Personnes</a>
        <a href="/organisations">Organisations</a>
        <?php endif; ?>
        <span class="auth-status">
            <?php if ($adminAuthenticated): ?><span class="badge badge-admin">Admin connecté</span><?php endif; ?>
            <?php if ($personAuthenticated): ?><span class="badge badge-operator">Opérateur connecté</span><?php endif; ?>
        </span>
        <a href="/logout">Déconnexion</a>
    </nav>
</header>
<?php else: ?>
<header class="console-header">
    <div class="brand">GAMAD Core<small>Console d'Exploitation — instrument de pilotage interne, pas un produit</small></div>
    <nav class="console-nav">
        <a href="/login">Connexion admin</a>
        <a href="/login-operateur">Connexion opérateur</a>
    </nav>
</header>
<?php endif; ?>
<main>
<?php
$flashError = \Gamad\Console\Lib\Session::flash('error');
$flashSuccess = \Gamad\Console\Lib\Session::flash('success');
?>
<?php if ($flashError !== null): ?>
<div class="flash flash-error"><?= h($flashError) ?></div>
<?php endif; ?>
<?php if ($flashSuccess !== null): ?>
<div class="flash flash-success"><?= h($flashSuccess) ?></div>
<?php endif; ?>
