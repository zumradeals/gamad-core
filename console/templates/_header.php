<?php
/** @var bool $authenticated */
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
<?php if ($authenticated): ?>
<header class="console-header">
    <div class="brand">GAMAD Core<small>Console d'Exploitation — instrument de pilotage interne, pas un produit</small></div>
    <nav class="console-nav">
        <a href="/">Tableau de bord</a>
        <a href="/identities">Identités</a>
        <a href="/dead-letters">Dead letters</a>
        <a href="/logout">Déconnexion</a>
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
