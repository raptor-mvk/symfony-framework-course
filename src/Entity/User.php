<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping;

/**
 *
 * @Mapping\Table(name="`user`")
 * @Mapping\Entity(repositoryClass="App\Repository\UserRepository")
 * @Mapping\HasLifecycleCallbacks()
 *
 */
class User
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
     * @Mapping\Column(type="string", length=32, nullable=false, unique=true)
     */
    private $login;

    /**
     * @var string
     *
     * @Mapping\Column(type="string", length=32, nullable=false)
     */
    private $password;

    /**
     * @var int
     *
     * @Mapping\Column(type="integer", nullable=false)
     */
    private $age;

    /**
     * @var bool
     *
     * @Mapping\Column(type="boolean", nullable=false)
     */
    private $isActive;

    /**
     * @var Organization
     *
     * @Mapping\ManyToOne(targetEntity="Organization", inversedBy="groupTest", cascade={"persist"})
     */
    private $organization;

    /**
     * @Mapping\ManyToMany(targetEntity="Organization")
     * @Mapping\JoinTable(name="user_organization")
     * @var ArrayCollection
     */
    private $linkedOrganizations;

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
    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * @param string $login
     */
    public function setLogin(string $login): void
    {
        $this->login = $login;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return int
     */
    public function getAge()
    {
        return $this->age;
    }

    /**
     * @param int $age
     */
    public function setAge($age)
    {
        $this->age = $age;
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->isActive;
    }

    /**
     * @param bool $isActive
     */
    public function setIsActive($isActive)
    {
        $this->isActive = $isActive;
    }

    /**
     * @return Organization
     */
    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    /**
     * @param Organization $organization
     */
    public function setOrganization(Organization $organization): void
    {
        $this->organization = $organization;
    }

    /**
     * @return Collection
     */
    public function getLinkedOrganizations(): Collection
    {
        return $this->linkedOrganizations;
    }

    /**
     * @param Collection $linkedOrganizations
     */
    public function setLinkedOrganizations(Collection $linkedOrganizations): void
    {
        $this->linkedOrganizations = $linkedOrganizations;
    }

    public function addLinkedOrganization(Organization $organization) {
        $this->linkedOrganizations->add($organization);
    }

    public function removeLinkedOrganization(Organization $organization) {
        $this->linkedOrganizations->removeElement($organization);
    }
}