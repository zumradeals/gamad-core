<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Http;

use DomainException;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateDepartment;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateDepartmentHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateMembership;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateOrganization;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateOrganizationHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\EndMembership;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\EndMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\ResumeMembership;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\ResumeMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\SuspendMembership;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\SuspendMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\DepartmentNotFound;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\IdentityNotEligibleForOrganization;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\MembershipAlreadyActive;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\MembershipNotFound;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\OrganizationAlreadyExists;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\OrganizationNotFound;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\ParentOrganizationNotEligible;
use Gamad\Core\OrganizationsAndMemberships\Application\Exception\PersonNotFound;
use Gamad\Core\OrganizationsAndMemberships\Domain\Department;
use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationRepository;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\Response;
use InvalidArgumentException;
use ValueError;

final readonly class OrganizationsAndMembershipsHttpController
{
    public function __construct(
        private CreateOrganizationHandler $createOrganization,
        private OrganizationRepository $organizations,
        private CreateDepartmentHandler $createDepartment,
        private CreateMembershipHandler $createMembership,
        private MembershipRepository $memberships,
        private SuspendMembershipHandler $suspendMembership,
        private ResumeMembershipHandler $resumeMembership,
        private EndMembershipHandler $endMembership,
    ) {
    }

    public function createOrganization(Request $request): Response
    {
        $body = $this->decode($request);

        try {
            $organization = ($this->createOrganization)(new CreateOrganization(
                identityId: (string) ($body['identity_id'] ?? ''),
                name: (string) ($body['name'] ?? ''),
                parentId: isset($body['parent_id']) && $body['parent_id'] !== null ? (string) $body['parent_id'] : null,
            ));
        } catch (IdentityNotEligibleForOrganization|OrganizationAlreadyExists|ParentOrganizationNotEligible $exception) {
            return Response::json(409, ['error' => 'organization_registration_rejected', 'detail' => $exception->getMessage()]);
        }

        return Response::json(201, $this->serializeOrganization($organization));
    }

    public function getOrganization(Request $request): Response
    {
        try {
            $organization = $this->organizations->findById(new OrganizationId($request->pathParameters['orgId']));
        } catch (InvalidArgumentException) {
            return Response::json(404, ['error' => 'organization_not_found']);
        }

        return $organization === null
            ? Response::json(404, ['error' => 'organization_not_found'])
            : Response::json(200, $this->serializeOrganization($organization));
    }

    public function getOrganizationChildren(Request $request): Response
    {
        try {
            $parentId = new OrganizationId($request->pathParameters['orgId']);
        } catch (InvalidArgumentException) {
            return Response::json(200, ['items' => []]);
        }

        $children = array_map(
            fn (Organization $child): array => $this->serializeOrganization($child),
            $this->organizations->findChildren($parentId),
        );

        return Response::json(200, ['items' => $children]);
    }

    public function createDepartment(Request $request): Response
    {
        $body = $this->decode($request);

        try {
            $department = ($this->createDepartment)(new CreateDepartment(
                organizationId: $request->pathParameters['orgId'],
                name: (string) ($body['name'] ?? ''),
            ));
        } catch (OrganizationNotFound $exception) {
            return Response::json(404, ['error' => 'organization_not_found', 'detail' => $exception->getMessage()]);
        }

        return Response::json(201, $this->serializeDepartment($department));
    }

    public function createMembership(Request $request): Response
    {
        $body = $this->decode($request);

        try {
            $membership = ($this->createMembership)(new CreateMembership(
                personId: (string) ($body['person_id'] ?? ''),
                organizationId: $request->pathParameters['orgId'],
                membershipType: (string) ($body['membership_type'] ?? ''),
                departmentId: isset($body['department_id']) && $body['department_id'] !== null ? (string) $body['department_id'] : null,
            ));
        } catch (PersonNotFound|OrganizationNotFound|DepartmentNotFound $exception) {
            return Response::json(404, ['error' => 'membership_registration_rejected', 'detail' => $exception->getMessage()]);
        } catch (MembershipAlreadyActive $exception) {
            return Response::json(409, ['error' => 'membership_already_active', 'detail' => $exception->getMessage()]);
        } catch (ValueError) {
            return Response::json(404, ['error' => 'membership_registration_rejected', 'detail' => 'Unknown membership_type.']);
        }

        return Response::json(201, $this->serializeMembership($membership));
    }

    public function listOrganizationMemberships(Request $request): Response
    {
        try {
            $organizationId = new OrganizationId($request->pathParameters['orgId']);
        } catch (InvalidArgumentException) {
            return Response::json(200, ['items' => []]);
        }

        $items = array_map(
            fn (Membership $membership): array => $this->serializeMembership($membership),
            $this->memberships->findActiveByOrganization($organizationId),
        );

        return Response::json(200, ['items' => $items]);
    }

    public function suspendMembership(Request $request): Response
    {
        try {
            $membership = ($this->suspendMembership)(new SuspendMembership($request->pathParameters['membershipId']));
        } catch (MembershipNotFound $exception) {
            return Response::json(404, ['error' => 'membership_not_found', 'detail' => $exception->getMessage()]);
        } catch (DomainException $exception) {
            return Response::json(409, ['error' => 'invalid_lifecycle_transition', 'detail' => $exception->getMessage()]);
        }

        return Response::json(200, $this->serializeMembership($membership));
    }

    public function resumeMembership(Request $request): Response
    {
        try {
            $membership = ($this->resumeMembership)(new ResumeMembership($request->pathParameters['membershipId']));
        } catch (MembershipNotFound $exception) {
            return Response::json(404, ['error' => 'membership_not_found', 'detail' => $exception->getMessage()]);
        } catch (DomainException $exception) {
            return Response::json(409, ['error' => 'invalid_lifecycle_transition', 'detail' => $exception->getMessage()]);
        }

        return Response::json(200, $this->serializeMembership($membership));
    }

    public function endMembership(Request $request): Response
    {
        try {
            $membership = ($this->endMembership)(new EndMembership($request->pathParameters['membershipId']));
        } catch (MembershipNotFound $exception) {
            return Response::json(404, ['error' => 'membership_not_found', 'detail' => $exception->getMessage()]);
        } catch (DomainException $exception) {
            return Response::json(409, ['error' => 'invalid_lifecycle_transition', 'detail' => $exception->getMessage()]);
        }

        return Response::json(200, $this->serializeMembership($membership));
    }

    /** @return array<string, mixed> */
    private function decode(Request $request): array
    {
        $decoded = json_decode($request->body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function serializeOrganization(Organization $organization): array
    {
        return [
            'organization_id' => (string) $organization->id(),
            'parent_id' => $organization->parentId() !== null ? (string) $organization->parentId() : null,
            'name' => $organization->name(),
            'status' => $organization->status()->value,
            'founded_at' => $organization->foundedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeDepartment(Department $department): array
    {
        return [
            'department_id' => (string) $department->id(),
            'name' => $department->name(),
            'status' => $department->status()->value,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeMembership(Membership $membership): array
    {
        return [
            'membership_id' => (string) $membership->id(),
            'person_id' => $membership->personId(),
            'organization_id' => (string) $membership->organizationId(),
            'department_id' => $membership->departmentId() !== null ? (string) $membership->departmentId() : null,
            'membership_type' => $membership->membershipType()->value,
            'status' => $membership->status()->value,
            'started_at' => $membership->startedAt()->format(DATE_ATOM),
            'ended_at' => $membership->endedAt()?->format(DATE_ATOM),
        ];
    }
}
