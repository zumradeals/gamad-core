@extends('layouts.console')

@section('title', 'Email & SMS')

@section('content')
@php
    $email = $configuration['email'] ?? [];
    $sms = $configuration['sms'] ?? [];
@endphp

<header class="page-header">
    <div>
        <p class="eyebrow">Paramètres</p>
        <h1 class="page-title">Email &amp; SMS</h1>
        <p class="page-subtitle">
            Configurez ici comment GAMAD envoie les codes de vérification des comptes. Les mots de passe et jetons
            enregistrés ne sont jamais réaffichés.
        </p>
    </div>
</header>

@if($errors->any())
    <div class="form-error" role="alert" style="margin-bottom:20px">
        <span aria-hidden="true">!</span>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

<section class="hero-status" aria-labelledby="etat-canaux">
    <div>
        <p class="eyebrow">État actuel</p>
        <h2 class="hero-status__title" id="etat-canaux">Les canaux restent sous votre contrôle.</h2>
        <p class="hero-status__copy">
            Email : <strong>{{ ($email['enabled'] ?? false) ? 'activé' : 'désactivé' }}</strong>
            · SMS : <strong>{{ ($sms['enabled'] ?? false) ? 'activé' : 'désactivé' }}</strong>
            @if(!empty($configuration['updated_at']))
                · dernière modification {{ $configuration['updated_at'] }}
            @endif
        </p>
    </div>
    <div class="health-orbit">
        <div class="health-orbit__ring">
            <span>
                <span class="health-orbit__state">Console</span>
                <span class="health-orbit__label">Configuration chiffrée</span>
            </span>
        </div>
    </div>
</section>

<form method="POST" action="{{ route('console.parametres.verification.update') }}" style="margin-top:20px">
    @csrf

    <div class="dashboard-grid">
        <section class="card span-6">
            <div class="card__header">
                <div>
                    <p class="eyebrow">Canal 1</p>
                    <h2 class="card__title">Email</h2>
                </div>
            </div>
            <div class="card__body">
                <label style="display:flex;align-items:center;gap:10px;margin-bottom:20px;font-weight:800">
                    <input type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled', $email['enabled'] ?? false))>
                    Activer l’envoi des codes par email
                </label>

                <input type="hidden" name="email_driver" value="smtp">

                <label class="field-label" for="email_host">Serveur SMTP</label>
                <input class="field-input" id="email_host" name="email_host" value="{{ old('email_host', $email['host'] ?? '') }}" placeholder="smtp.votre-fournisseur.com">

                <div class="form-grid" style="margin-top:14px">
                    <div>
                        <label class="field-label" for="email_port">Port</label>
                        <input class="field-input" type="number" id="email_port" name="email_port" value="{{ old('email_port', $email['port'] ?? 587) }}" min="1" max="65535">
                    </div>
                    <div>
                        <label class="field-label" for="email_scheme">Sécurité</label>
                        <select class="field-input" id="email_scheme" name="email_scheme">
                            <option value="smtp" @selected(old('email_scheme', $email['scheme'] ?? 'smtp') === 'smtp')>SMTP avec chiffrement automatique (recommandé)</option>
                            <option value="smtps" @selected(old('email_scheme', $email['scheme'] ?? 'smtp') === 'smtps')>SMTPS direct</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid" style="margin-top:14px">
                    <div>
                        <label class="field-label" for="email_username">Utilisateur SMTP</label>
                        <input class="field-input" id="email_username" name="email_username" autocomplete="off" value="{{ old('email_username', $email['username'] ?? '') }}">
                    </div>
                    <div>
                        <label class="field-label" for="email_password">Mot de passe SMTP</label>
                        <input class="field-input" type="password" id="email_password" name="email_password" autocomplete="new-password" placeholder="{{ ($email['password_present'] ?? false) ? 'Déjà enregistré — laisser vide pour conserver' : 'Mot de passe ou clé SMTP' }}">
                    </div>
                </div>

                <div class="form-grid" style="margin-top:14px">
                    <div>
                        <label class="field-label" for="email_from_address">Adresse d’envoi</label>
                        <input class="field-input" type="email" id="email_from_address" name="email_from_address" value="{{ old('email_from_address', $email['from_address'] ?? '') }}" placeholder="no-reply@dgafrique.com">
                    </div>
                    <div>
                        <label class="field-label" for="email_from_name">Nom d’envoi</label>
                        <input class="field-input" id="email_from_name" name="email_from_name" value="{{ old('email_from_name', $email['from_name'] ?? 'GAMAD') }}">
                    </div>
                </div>

                <label class="field-label" for="email_subject" style="margin-top:14px">Objet du message</label>
                <input class="field-input" id="email_subject" name="email_subject" value="{{ old('email_subject', $email['subject'] ?? 'Votre code de vérification GAMAD') }}">

                <p class="field-help" style="margin-top:12px">
                    Le mot de passe SMTP est chiffré avec la clé de l’application et n’est jamais affiché après enregistrement.
                </p>
            </div>
        </section>

        <section class="card span-6">
            <div class="card__header">
                <div>
                    <p class="eyebrow">Canal 2</p>
                    <h2 class="card__title">SMS</h2>
                </div>
            </div>
            <div class="card__body">
                <label style="display:flex;align-items:center;gap:10px;margin-bottom:20px;font-weight:800">
                    <input type="checkbox" name="sms_enabled" value="1" @checked(old('sms_enabled', $sms['enabled'] ?? false))>
                    Activer l’envoi des codes par SMS
                </label>

                <label class="field-label" for="sms_relay_url">Adresse HTTPS du relais SMS</label>
                <input class="field-input" type="url" id="sms_relay_url" name="sms_relay_url" value="{{ old('sms_relay_url', $sms['relay_url'] ?? '') }}" placeholder="https://sms.votre-service.com/send">

                <label class="field-label" for="sms_relay_token" style="margin-top:14px">Jeton du relais</label>
                <input class="field-input" type="password" id="sms_relay_token" name="sms_relay_token" autocomplete="new-password" placeholder="{{ ($sms['token_present'] ?? false) ? 'Déjà enregistré — laisser vide pour conserver' : 'Jeton secret du relais SMS' }}">

                <div class="form-grid" style="margin-top:14px">
                    <div>
                        <label class="field-label" for="sms_sender">Nom expéditeur</label>
                        <input class="field-input" id="sms_sender" name="sms_sender" value="{{ old('sms_sender', $sms['sender'] ?? 'GAMAD') }}" maxlength="32">
                    </div>
                    <div>
                        <label class="field-label" for="sms_timeout">Délai maximum (secondes)</label>
                        <input class="field-input" type="number" id="sms_timeout" name="sms_timeout" value="{{ old('sms_timeout', $sms['timeout'] ?? 8) }}" min="2" max="15">
                    </div>
                </div>

                <p class="field-help" style="margin-top:12px">
                    Le Core reste indépendant de l’opérateur : le relais peut utiliser Orange, MTN, Infobip, Twilio ou un autre fournisseur.
                </p>
            </div>
        </section>
    </div>

    <div style="display:flex;justify-content:flex-end;margin-top:20px">
        <button class="button button--primary" type="submit">Enregistrer la configuration</button>
    </div>
</form>

<div class="dashboard-grid" style="margin-top:20px">
    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Tester l’email</h2></div>
        <div class="card__body">
            <form method="POST" action="{{ route('console.parametres.verification.test-email') }}">
                @csrf
                <label class="field-label" for="destination_email">Adresse qui recevra le test</label>
                <input class="field-input" type="email" id="destination_email" name="destination_email" placeholder="vous@exemple.com" required>
                <button class="button" type="submit" style="margin-top:14px">Envoyer un email de test</button>
            </form>
        </div>
    </section>

    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Tester le SMS</h2></div>
        <div class="card__body">
            <form method="POST" action="{{ route('console.parametres.verification.test-sms') }}">
                @csrf
                <label class="field-label" for="destination_sms">Numéro international</label>
                <input class="field-input" id="destination_sms" name="destination_sms" placeholder="+2250700000000" required>
                <button class="button" type="submit" style="margin-top:14px">Envoyer un SMS de test</button>
            </form>
        </div>
    </section>
</div>
@endsection
