<?php
declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\ORM\Mapping;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 *
 * @Mapping\Table(
 *     name="feed",
 *     uniqueConstraints={@Mapping\UniqueConstraint(columns={"reader_id"})}
 * )
 * @Mapping\Entity
 * @Mapping\HasLifecycleCallbacks
 */
class Feed
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
     *     @Mapping\JoinColumn(name="reader_id", referencedColumnName="id")
     * })
     */
    private $reader;

    /**
     * @var array | null
     *
     * @Mapping\Column(type="json_array", nullable=true)
     */
    private $tweets;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return User
     */
    public function getReader(): User
    {
        return $this->reader;
    }

    /**
     * @param User $reader
     */
    public function setReader(User $reader): void
    {
        $this->reader = $reader;
    }

    /**
     * @return array|null
     */
    public function getTweets(): ?array
    {
        return $this->tweets;
    }

    /**
     * @param array|null $tweets
     */
    public function setTweets(?array $tweets): void
    {
        $this->tweets = $tweets;
    }
}
