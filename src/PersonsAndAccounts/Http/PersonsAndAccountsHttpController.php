<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Http;

use Gamad\Core\PersonsAndAccounts\Application\Command\Authenticate;
use Gamad\Core\PersonsAndAccounts\Application\Command\AuthenticateHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterPerson;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterPersonHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterUserAccount;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterUserAccountHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\RevokeSession;
use Gamad\Core\PersonsAndAccounts\Application\Command\RevokeSessionHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\SetPassword;
use Gamad\Core\PersonsAndAccounts\Application\Command\SetPasswordHandler;
use Gamad\Core\PersonsAndAccounts\Application\Exception\AuthenticationFailed;
use Gamad\Core\PersonsAndAccounts\Application\Exception\IdentityNotEligibleForPerson;
use Gamad\Core\PersonsAndAccounts\Application\Exception\PersonAlreadyExists;
use Gamad\Core\PersonsAndAccounts\Application\Exception\PersonNotFound;
use Gamad\Core\PersonsAndAccounts\Application\Exception\SessionNotFound;
use Gamad\Core\PersonsAndAccounts\Application\Exception\UserAccountAlreadyExists;
use Gamad\Core\PersonsAndAccounts\Application\Exception\UserAccountNotFound;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\PersonRepository;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\Response;
use InvalidArgumentException;

final readonly class PersonsAndAccountsHttpController
{
    public function __construct(
        private RegisterPersonHandler $registerPerson,
        private PersonRepository $persons,
        private RegisterUserAccountHandler $registerUserAccount,
        private SetPasswordHandler $setPassword,
        private AuthenticateHandler $authenticate,
        private RevokeSessionHandler $revokeSession,
    ) {
    }

    public function registerPerson(Request $request): Response
    {
        $body = $this->decode($request);

        try {
            $person = ($this->registerPerson)(new RegisterPerson(
                identityId: (string) ($body['identity_id'] ?? ''),
                declaredName: (string) ($body['declared_name'] ?? ''),
            ));
        } catch (IdentityNotEligibleForPerson|PersonAlreadyExists $exception) {
            return Response::json(409, ['error' => 'person_registration_rejected', 'detail' => $exception->getMessage()]);
        }

        return Response::json(201, $this->serializePerson($person));
    }

    public function getPerson(Request $request): Response
    {
        try {
            $personId = new PersonId($request->pathParameters['personId']);
        } catch (InvalidArgumentException) {
            return Response::json(404, ['error' => 'person_not_found']);
        }

        $person = $this->persons->findById($personId);

        return $person === null
            ? Response::json(404, ['error' => 'person_not_found'])
            : Response::json(200, $this->serializePerson($person));
    }

    public function registerUserAccount(Request $request): Response
    {
        try {
            $account = ($this->registerUserAccount)(new RegisterUserAccount($request->pathParameters['personId']));
        } catch (PersonNotFound $exception) {
            return Response::json(404, ['error' => 'person_not_found', 'detail' => $exception->getMessage()]);
        } catch (UserAccountAlreadyExists $exception) {
            return Response::json(409, ['error' => 'user_account_already_exists', 'detail' => $exception->getMessage()]);
        }

        return Response::json(201, $this->serializeAccount($account));
    }

    public function setPassword(Request $request): Response
    {
        $body = $this->decode($request);

        try {
            ($this->setPassword)(new SetPassword($request->pathParameters['accountId'], (string) ($body['password'] ?? '')));
        } catch (UserAccountNotFound $exception) {
            return Response::json(404, ['error' => 'user_account_not_found', 'detail' => $exception->getMessage()]);
        }

        return new Response(204, '');
    }

    public function login(Request $request): Response
    {
        $body = $this->decode($request);

        try {
            $result = ($this->authenticate)(new Authenticate(
                personId: (string) ($body['person_id'] ?? ''),
                plainPassword: (string) ($body['password'] ?? ''),
            ));
        } catch (AuthenticationFailed $exception) {
            return Response::json(401, ['error' => 'invalid_credentials', 'detail' => $exception->getMessage()]);
        }

        return Response::json(200, [
            'token' => $result->token,
            'expires_at' => $result->session->expiresAt()->format(DATE_ATOM),
        ]);
    }

    public function revokeSession(Request $request): Response
    {
        try {
            ($this->revokeSession)(new RevokeSession($request->pathParameters['sessionId']));
        } catch (SessionNotFound $exception) {
            return Response::json(404, ['error' => 'session_not_found', 'detail' => $exception->getMessage()]);
        }

        return new Response(204, '');
    }

    /** @return array<string, mixed> */
    private function decode(Request $request): array
    {
        $decoded = json_decode($request->body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function serializePerson(Person $person): array
    {
        return [
            'person_id' => (string) $person->id(),
            'declared_name' => $person->declaredName(),
            'status' => $person->status()->value,
            'registered_at' => $person->registeredAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeAccount(UserAccount $account): array
    {
        return [
            'account_id' => (string) $account->id(),
            'person_id' => (string) $account->personId(),
            'status' => $account->status()->value,
            'created_at' => $account->createdAt()->format(DATE_ATOM),
        ];
    }
}
