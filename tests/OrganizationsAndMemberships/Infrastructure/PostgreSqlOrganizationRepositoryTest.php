<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\OrganizationsAndMemberships\Infrastructure;

use DateTimeImmutable;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentId;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationStatus;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\PostgreSqlOrganizationLookup;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\PostgreSqlOrganizationRepository;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlOrganizationRepositoryTest extends TestCase
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
        $this->connection->exec('DROP TABLE IF EXISTS persons CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS identities CASCADE');

        foreach ([1, 11, 15, 19, 20, 21] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }
    }

    public function test_it_saves_and_finds_an_organization_with_its_departments(): void
    {
        $this->insertOrganizationIdentity('GAM-GAT-ORG-000001');
        $repository = new PostgreSqlOrganizationRepository($this->connection);

        $organization = Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS', new DateTimeImmutable('2026-07-15T00:00:00+00:00'));
        $organization->addDepartment(DepartmentId::generate(), 'Direction Générale');
        $repository->save($organization);

        $found = $repository->findById(new OrganizationId('GAM-GAT-ORG-000001'));

        self::assertNotNull($found);
        self::assertSame('GAMAD SAS', $found->name());
        self::assertSame(OrganizationStatus::Active, $found->status());
        self::assertNull($found->parentId());
        self::assertCount(1, $found->departments());
        self::assertSame('Direction Générale', $found->departments()[0]->name());
        self::assertSame(DepartmentStatus::Active, $found->departments()[0]->status());
        self::assertTrue($repository->exists(new OrganizationId('GAM-GAT-ORG-000001')));
    }

    public function test_it_rejects_a_root_organization_that_is_not_gamad_sas(): void
    {
        $this->insertOrganizationIdentity('GAM-GAT-ORG-900099');
        $repository = new PostgreSqlOrganizationRepository($this->connection);

        $organization = Organization::create(new OrganizationId('GAM-GAT-ORG-900099'), null, 'Impostor Root');

        $this->expectException(\PDOException::class);

        $repository->save($organization);
    }

    public function test_it_finds_direct_children_and_resolves_the_ancestry_chain_across_two_levels(): void
    {
        $this->insertOrganizationIdentity('GAM-GAT-ORG-000001');
        $this->insertOrganizationIdentity('GAM-GAT-ORG-000002');
        $this->insertOrganizationIdentity('GAM-GAT-ORG-000003');
        $repository = new PostgreSqlOrganizationRepository($this->connection);

        $root = Organization::create(new OrganizationId('GAM-GAT-ORG-000001'), null, 'GAMAD SAS');
        $repository->save($root);
        $child = Organization::create(new OrganizationId('GAM-GAT-ORG-000002'), new OrganizationId('GAM-GAT-ORG-000001'), 'GAMAD Technologie');
        $repository->save($child);
        $grandchild = Organization::create(new OrganizationId('GAM-GAT-ORG-000003'), new OrganizationId('GAM-GAT-ORG-000002'), 'Structure fictive');
        $repository->save($grandchild);

        $rootChildren = $repository->findChildren(new OrganizationId('GAM-GAT-ORG-000001'));
        self::assertCount(1, $rootChildren);
        self::assertSame('GAM-GAT-ORG-000002', (string) $rootChildren[0]->id());

        $lookup = new PostgreSqlOrganizationLookup($this->connection);
        $chain = $lookup->ancestryChain(new OrganizationId('GAM-GAT-ORG-000003'));

        self::assertSame(['GAM-GAT-ORG-000001', 'GAM-GAT-ORG-000002', 'GAM-GAT-ORG-000003'], $chain);
    }

    private function insertOrganizationIdentity(string $id): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO identities (id, type, status, registered_at) VALUES (:id, :type, :status, :registered_at)'
        );
        $statement->execute([
            'id' => $id,
            'type' => 'organization',
            'status' => 'active',
            'registered_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
