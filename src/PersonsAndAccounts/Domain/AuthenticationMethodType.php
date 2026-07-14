<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

/** Extensible by design (ADR-0018 §4) — oidc_external and future types slot in without touching UserAccount or Session. */
enum AuthenticationMethodType: string
{
    case Password = 'password';
    case OidcExternal = 'oidc_external';
}
