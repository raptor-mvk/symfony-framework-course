<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping;

/**
 *
 * @Mapping\Table(name="organization")
 * @Mapping\Entity(repositoryClass="App\Repository\OrganizationRepository")
 * @Mapping\HasLifecycleCallbacks()
 *
 */
class Organization
{
    use CreatedAtTrait, UpdatedAtTrait;

    /**
     * @Mapping\Column(name="id", type="bigint", unique=true)
     * @Mapping\Id
     * @Mapping\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @Mapping\Column(type="string", length=32, nullable=false)
     */
    private $name;

    /**
     * @var User[]
     *
     * @Mapping\OneToMany(targetEntity="User", mappedBy="organization", cascade={"persist"})
     */
    public $users;

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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * @param User[] $users
     */
    public function setUsers(array $users): void
    {
        $this->users = $users;
    }

    /**
     * @return ArrayCollection
     */
    public function getLinkedUsers(): ArrayCollection
    {
        return $this->linkedUsers;
    }

    /**
     * @param ArrayCollection $linkedUsers
     */
    public function setLinkedUsers(ArrayCollection $linkedUsers): void
    {
        $this->linkedUsers = $linkedUsers;
    }
}