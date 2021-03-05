<?php

namespace App\Service;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;

class OrganizationService
{
    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function saveOrganization(string $name): ?int
    {
        $organization = new Organization();
        $organization->setName($name);
        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        return $organization->getId();
    }

    public function updateOrganization(int $organizationId, string $name): bool
    {
        /** @var OrganizationRepository $organizationRepository */
        $organizationRepository = $this->entityManager->getRepository(Organization::class);
        /** @var Organization $organization */
        $organization = $organizationRepository->find($organizationId);
        if ($organization === null) {
            return false;
        }
        $organization->setName($name);
        $this->entityManager->flush();

        return true;
    }

    public function deleteOrganization(int $organizationId): bool
    {
        /** @var OrganizationRepository $organizationRepository */
        $organizationRepository = $this->entityManager->getRepository(Organization::class);
        /** @var Organization $organization */
        $organization = $organizationRepository->find($organizationId);
        if ($organization === null) {
            return false;
        }
        $this->entityManager->remove($organization);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return Organization[]
     */
    public function getOrganizations(int $page, int $perPage): array
    {
        /** @var OrganizationRepository $organizationRepository */
        $organizationRepository = $this->entityManager->getRepository(Organization::class);

        return $organizationRepository->getOrganizations($page, $perPage);
    }

    public function getById(int $organizationId): ?Organization
    {
        $organizationRepository = $this->entityManager->getRepository(Organization::class);
        /** @var Organization $organization */
        $organization = $organizationRepository->find($organizationId);
        return $organization;
    }
}