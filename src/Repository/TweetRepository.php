<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tweet;
use Doctrine\ORM\EntityRepository;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class TweetRepository extends EntityRepository
{
    /**
     * @param int[] $authorIds
     *
     * @return Tweet[]
     */
    public function getByAuthorIds(array $authorIds, int $count): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('t')
            ->from($this->getClassName(), 't')
            ->where($qb->expr()->in('IDENTITY(t.author)', ':authorIds'))
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($count);

        $qb->setParameter('authorIds', $authorIds);

        return $qb->getQuery()->getResult();
    }
}