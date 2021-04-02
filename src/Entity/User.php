<?php
declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as JMS;
use JsonException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Table(name="`user`")
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User implements HasMetaTimestampsInterface, UserInterface
{
    public const EMAIL_NOTIFICATION = 'email';
    public const SMS_NOTIFICATION = 'sms';

    /**
     * @ORM\Column(name="id", type="bigint", unique=true)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @JMS\Groups({"user2"})
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=32, nullable=false, unique=true)
     * @JMS\Groups({"user1"})
     */
    private string $login;

    /**
     * @ORM\Column(name="created_at", type="datetime", nullable=false)
     * @Gedmo\Timestampable(on="create")
     */
    private DateTime $createdAt;

    /**
     * @ORM\Column(name="updated_at", type="datetime", nullable=false)
     * @Gedmo\Timestampable(on="update")
     */
    private DateTime $updatedAt;

    /**
     * @ORM\OneToMany(targetEntity="Tweet", mappedBy="author")
     */
    private Collection $tweets;

    /**
     * @ORM\ManyToMany(targetEntity="User", mappedBy="followers")
     */
    private Collection $authors;

    /**
     * @ORM\ManyToMany(targetEntity="User", inversedBy="authors")
     * @ORM\JoinTable(
     *     name="author_follower",
     *     joinColumns={@ORM\JoinColumn(name="author_id", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="follower_id", referencedColumnName="id")}
     * )
     */
    private Collection $followers;

    /**
     * @ORM\Column(type="string", length=120, nullable=false)
     */
    private string $password;

    /**
     * @ORM\Column(type="string", length=1024, nullable=false)
     */
    private string $roles;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @JMS\Type("int")
     * @JMS\Groups({"user1"})
     */
    private int $age;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @JMS\Type("bool")
     * @JMS\Groups({"user1"})
     * @JMS\SerializedName("isActive")
     */
    private bool $isActive;

    /**
     * @ORM\Column(type="string", length=32, nullable=true, unique=true)
     */
    private ?string $token = null;

    /**
     * @ORM\Column(type="string", length=11, nullable=true)
     */
    private ?string $phone;

    /**
     * @ORM\Column(type="string", length=128, nullable=true)
     */
    private ?string $email;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private ?string $preferred;

    public function __construct()
    {
        $this->tweets = new ArrayCollection();
        $this->authors = new ArrayCollection();
        $this->followers = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setLogin(string $login): void
    {
        $this->login = $login;
    }

    public function getCreatedAt(): DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(): void {
        $this->createdAt = new DateTime();
    }

    public function getUpdatedAt(): DateTime {
        return $this->updatedAt;
    }

    public function setUpdatedAt(): void {
        $this->updatedAt = new DateTime();
    }

    public function addTweet(Tweet $tweet): void
    {
        if (!$this->tweets->contains($tweet)) {
            $this->tweets->add($tweet);
        }
    }

    public function addFollower(User $follower): void
    {
        if (!$this->followers->contains($follower)) {
            $this->followers->add($follower);
        }
    }

    public function addAuthor(User $author): void
    {
        if (!$this->authors->contains($author)) {
            $this->authors->add($author);
        }
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return string[]
     *
     * @JMS\Groups({"user1"})
     * @JMS\Type("array")
     *
     * @throws JsonException
     */
    public function getRoles(): array
    {
        $roles = json_decode($this->roles, true, 512, JSON_THROW_ON_ERROR);
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param string[] $roles
     *
     * @throws JsonException
     */
    public function setRoles(array $roles): void
    {
        $this->roles = json_encode($roles, JSON_THROW_ON_ERROR);
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): void
    {
        $this->age = $age;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPreferred(): ?string
    {
        return $this->preferred;
    }

    public function setPreferred(string $preferred): void
    {
        $this->preferred = $preferred;
    }

    /**
     * @throws JsonException
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'login' => $this->login,
            'password' => $this->password,
            'roles' => $this->getRoles(),
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
            'tweets' => array_map(static fn(Tweet $tweet) => $tweet->toArray(), $this->tweets->toArray()),
            'followers' => array_map(static fn(User $user) => $user->getLogin(), $this->followers->toArray()),
            'authors' => array_map(static fn(User $user) => $user->getLogin(), $this->authors->toArray()),
        ];
    }

    public function getSalt()
    {
        // TODO: Implement getSalt() method.
    }

    public function getUsername()
    {
        return $this->login;
    }

    public function eraseCredentials()
    {
        // TODO: Implement eraseCredentials() method.
    }
}