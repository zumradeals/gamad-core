<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application;

/** ADR-0017 §3 — the realm is a startup configuration constant, never caller-supplied. */
interface CoreRealmProvider
{
    public function realm(): string;
}
