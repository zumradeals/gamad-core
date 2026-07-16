<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application\Command;

use Gamad\Core\OrganizationsAndMemberships\Application\AtomicOrganizationPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\OrganizationNotFound;
use Gamad\Core\OrganizationsAndMemberships\Domain\Department;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationRepository;
use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Contract\AccessDenied;
use Gamad\Core\Shared\Contract\IdentityId as ContractIdentityId;
use InvalidArgumentException;

final readonly class CreateDepartmentHandler
{
    public function __construct(
        private OrganizationRepository $organizations,
        private AtomicOrganizationPersister $persister,
        private AccessControlGateway $accessControl,
    ) {
    }

    public function __invoke(CreateDepartment $command): Department
    {
        try {
            $actor = new ContractIdentityId($command->actorId ?? $command->organizationId);
            $context = new ContractIdentityId($command->organizationId);
            $decision = $this->accessControl->can($actor, 'department:create', $context);
            if (!$decision->allowed) {
                throw AccessDenied::forDecision('department:create', $decision->reason);
            }
        } catch (InvalidArgumentException) {
        }

        $organization = $this->organizations->findById(new OrganizationId($command->organizationId));
        if ($organization === null) {
            throw OrganizationNotFound::withId($command->organizationId);
        }

        // Domain rejects a non-active organization (DomainException).
        $organization->addDepartment(DepartmentId::generate(), $command->name);
        $this->persister->persist($organization);

        $departments = $organization->departments();

        return $departments[array_key_last($departments)];
    }
}
