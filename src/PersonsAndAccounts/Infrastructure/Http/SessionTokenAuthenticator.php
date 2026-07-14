<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Infrastructure\Http;

use Gamad\Core\PersonsAndAccounts\Domain\SessionRepository;
use Gamad\Core\Shared\Http\AuthenticatedActor;
use Gamad\Core\Shared\Http\AuthenticationAdapter;
use Gamad\Core\Shared\Http\Request;

/**
 * Authenticates a request against this context's own Session aggregate —
 * never against the ADR-0011 administrative bootstrap tokens (Task 6).
 * The presented bearer token is hashed and looked up by hash, exactly like
 * a password never being stored or compared in the clear (ADR-0018 §1).
 */
final readonly class SessionTokenAuthenticator implements AuthenticationAdapter
{
    public function __construct(private SessionRepository $sessions)
    {
    }

    public function authenticate(Request $request): ?AuthenticatedActor
    {
        $header = $request->header('Authorization');
        if ($header === null || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = substr($header, 7);
        if ($token === '') {
            return null;
        }

        $session = $this->sessions->findByTokenHash(hash('sha256', $token));
        if ($session === null || !$session->isActive()) {
            return null;
        }

        return new AuthenticatedActor((string) $session->userAccountId(), []);
    }
}
