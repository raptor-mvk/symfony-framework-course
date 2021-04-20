<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 *
 * @Mapping\Table(
 *     name="subscription",
 *     uniqueConstraints={@Mapping\UniqueConstraint(columns={"author_id","follower_id"})},
 *     indexes={@Mapping\Index(columns={"follower_id", "author_id"})},
 * )
 * @Mapping\Entity
 * @Mapping\HasLifecycleCallbacks
 */
class Subscription
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @Mapping\Column(name="id", type="bigint", unique=true)
     * @Mapping\Id
     * @Mapping\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var User
     *
     * @Mapping\ManyToOne(targetEntity="User")
     * @Mapping\JoinColumns({
     *   @Mapping\JoinColumn(name="author_id", referencedColumnName="id")
     * })
     */
    private $author;

    /**
     * @var User
     *
     * @Mapping\ManyToOne(targetEntity="User")
     * @Mapping\JoinColumns({
     *   @Mapping\JoinColumn(name="follower_id", referencedColumnName="id")
     * })
     */
    private $follower;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }

    public function getFollower(): User
    {
        return $this->follower;
    }

    public function setFollower(User $follower): void
    {
        $this->follower = $follower;
    }
}