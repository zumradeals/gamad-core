<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethod;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountRepository;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountStatus;
use PDO;

final readonly class PostgreSqlUserAccountRepository implements UserAccountRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(UserAccount $account): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO user_accounts (id, person_id, status, created_at)
            VALUES (:id, :person_id, :status, :created_at)
            ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status
            SQL
        );
        $statement->execute([
            'id' => (string) $account->id(),
            'person_id' => (string) $account->personId(),
            'status' => $account->status()->value,
            'created_at' => $account->createdAt()->format(DATE_ATOM),
        ]);

        // Authentication methods are append-only (Domain never mutates one in
        // place), so every already-persisted method is naturally a no-op here.
        $methodStatement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO authentication_methods (id, user_account_id, method_type, credential_ref, added_at)
            VALUES (:id, :user_account_id, :method_type, :credential_ref, :added_at)
            ON CONFLICT (id) DO NOTHING
            SQL
        );
        foreach ($account->authenticationMethods() as $method) {
            $methodStatement->execute([
                'id' => (string) $method->id(),
                'user_account_id' => (string) $account->id(),
                'method_type' => $method->type()->value,
                'credential_ref' => $method->credentialRef(),
                'added_at' => $method->addedAt()->format(DATE_ATOM),
            ]);
        }
    }

    public function findById(UserAccountId $accountId): ?UserAccount
    {
        $statement = $this->connection->prepare(
            'SELECT id, person_id, status, created_at FROM user_accounts WHERE id = :id'
        );
        $statement->execute(['id' => (string) $accountId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findByPersonId(PersonId $personId): ?UserAccount
    {
        $statement = $this->connection->prepare(
            'SELECT id, person_id, status, created_at FROM user_accounts WHERE person_id = :person_id'
        );
        $statement->execute(['person_id' => (string) $personId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function existsForPerson(PersonId $personId): bool
    {
        $statement = $this->connection->prepare('SELECT EXISTS(SELECT 1 FROM user_accounts WHERE person_id = :person_id)');
        $statement->execute(['person_id' => (string) $personId]);

        return (bool) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): UserAccount
    {
        $methodsStatement = $this->connection->prepare(
            'SELECT id, method_type, credential_ref, added_at FROM authentication_methods WHERE user_account_id = :user_account_id ORDER BY added_at ASC'
        );
        $methodsStatement->execute(['user_account_id' => (string) $row['id']]);
        $methods = array_map(
            static fn (array $methodRow): AuthenticationMethod => new AuthenticationMethod(
                new AuthenticationMethodId((string) $methodRow['id']),
                AuthenticationMethodType::from((string) $methodRow['method_type']),
                (string) $methodRow['credential_ref'],
                new DateTimeImmutable((string) $methodRow['added_at']),
            ),
            $methodsStatement->fetchAll(PDO::FETCH_ASSOC),
        );

        return UserAccount::reconstitute(
            id: new UserAccountId((string) $row['id']),
            personId: new PersonId((string) $row['person_id']),
            status: UserAccountStatus::from((string) $row['status']),
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            authenticationMethods: $methods,
        );
    }
}
