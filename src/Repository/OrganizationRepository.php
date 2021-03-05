<?php

namespace App\Repository;

use App\Entity\Organization;
use Doctrine\ORM\EntityRepository;

class OrganizationRepository extends EntityRepository
{
    /**
     * @return Organization[]
     */
    public function getOrganizations(int $page, int $perPage): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('t')
            ->from($this->getClassName(), 't')
            ->orderBy('t.id', 'DESC')
            ->setFirstResult($perPage * $page)
            ->setMaxResults($perPage);

        return $qb->getQuery()->getResult();
    }
}