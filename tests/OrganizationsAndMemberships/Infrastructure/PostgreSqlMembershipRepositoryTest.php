<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Infrastructure;

use DateTimeImmutable;
use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipType;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\IdentityRegistry\PostgreSqlIdentityLookup;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\PostgreSqlMembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\PostgreSqlOrganizationRepository;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\PersonsAndAccounts\PostgreSqlPersonLookup;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlPersonRepository;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlMembershipRepositoryTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $dsn = getenv('GAMAD_TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set GAMAD_TEST_PG_DSN to run PostgreSQL integration tests.');
        }

        $this->connection = new PDO(
            $dsn,
            getenv('GAMAD_TEST_PG_USER') ?: null,
            getenv('GAMAD_TEST_PG_PASSWORD') ?: null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $this->connection->exec('DROP TABLE IF EXISTS memberships CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS departments CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS organizations CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS sessions CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS authentication_methods CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS user_accounts CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS persons CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS identities CASCADE');

        foreach ([1, 11, 12, 13, 14, 15, 16, 19, 20, 21] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }

        $this->insertIdentity('GAM-GAT-PER-900001', 'person');
        $this->insertIdentity('GAM-GAT-ORG-000001', 'organization');
        (new PostgreSqlPersonRepository($this->connection))->save(Person::register(new PersonId('GAM-GAT-PER-900001'), 'Amina Traoré'));
        (new PostgreSqlOrganizationRepository($this->connection))->save(Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS'));
    }

    public function test_it_saves_and_finds_a_membership_and_reflects_lifecycle_transitions(): void
    {
        $repository = new PostgreSqlMembershipRepository($this->connection);
        $membership = Membership::create(
            MembershipId::generate(),
            'GAM-GAT-PER-900001',
            new OrganizationId('GAM-GAT-ORG-000001'),
            MembershipType::GamadCitizen,
            startedAt: new DateTimeImmutable('2026-07-15T00:00:00+00:00'),
        );
        $repository->save($membership);

        $found = $repository->findById($membership->id());
        self::assertNotNull($found);
        self::assertSame(MembershipStatus::Active, $found->status());
        self::assertSame(MembershipType::GamadCitizen, $found->membershipType());

        $active = $repository->findActiveByPersonAndOrganization('GAM-GAT-PER-900001', new OrganizationId('GAM-GAT-ORG-000001'));
        self::assertNotNull($active);
        self::assertTrue($active->id()->equals($membership->id()));

        self::assertCount(1, $repository->findActiveByOrganization(new OrganizationId('GAM-GAT-ORG-000001')));
        self::assertCount(1, $repository->findActiveByPerson('GAM-GAT-PER-900001'));

        $membership->end(new DateTimeImmutable('2026-08-01T00:00:00+00:00'));
        $repository->save($membership);

        $reloaded = $repository->findById($membership->id());
        self::assertSame(MembershipStatus::Ended, $reloaded->status());
        self::assertNotNull($reloaded->endedAt());
        self::assertNull($repository->findActiveByPersonAndOrganization('GAM-GAT-PER-900001', new OrganizationId('GAM-GAT-ORG-000001')));
    }

    public function test_it_rejects_a_second_active_membership_for_the_same_pair_at_the_database_level(): void
    {
        $repository = new PostgreSqlMembershipRepository($this->connection);
        $repository->save(Membership::create(MembershipId::generate(), 'GAM-GAT-PER-900001', new OrganizationId('GAM-GAT-ORG-000001'), MembershipType::GamadCitizen));

        $this->expectException(\PDOException::class);

        $repository->save(Membership::create(MembershipId::generate(), 'GAM-GAT-PER-900001', new OrganizationId('GAM-GAT-ORG-000001'), MembershipType::OrdinaryCitizen));
    }

    public function test_lookups_read_the_identities_and_persons_tables_directly(): void
    {
        $identityLookup = new PostgreSqlIdentityLookup($this->connection);
        $result = $identityLookup->find('GAM-GAT-ORG-000001');
        self::assertNotNull($result);
        self::assertSame('organization', $result->type);
        self::assertSame('active', $result->status);
        self::assertNull($identityLookup->find('GAM-GAT-ORG-999999'));

        $personLookup = new PostgreSqlPersonLookup($this->connection);
        self::assertTrue($personLookup->exists('GAM-GAT-PER-900001'));
        self::assertFalse($personLookup->exists('GAM-GAT-PER-999999'));
    }

    private function insertIdentity(string $id, string $type): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO identities (id, type, status, registered_at) VALUES (:id, :type, :status, :registered_at)'
        );
        $statement->execute([
            'id' => $id,
            'type' => $type,
            'status' => 'active',
            'registered_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
