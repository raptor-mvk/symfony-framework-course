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
 * @Mapping\Table(name="`user`")
 * @Mapping\Entity
 * @Mapping\HasLifecycleCallbacks
 */
class User
{
    public const EMAIL_NOTIFICATION = 'email';
    public const SMS_NOTIFICATION = 'sms';

    use CreatedAtTrait;
    use UpdatedAtTrait;

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
    private $login;

    /**
     * @var string
     *
     * @Mapping\Column(type="string", length=11, nullable=false)
     */
    private $phone;

    /**
     * @var string
     *
     * @Mapping\Column(type="string", length=128, nullable=false)
     */
    private $email;

    /**
     * @var string
     *
     * @Mapping\Column(type="string", length=10, nullable=false)
     */
    private $preferred;

    public function getId(): int
    {
        return (int)$this->id;
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

    /**
     * @return string
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * @param string $phone
     */
    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getPreferred(): string
    {
        return $this->preferred;
    }

    /**
     * @param string $preferred
     */
    public function setPreferred(string $preferred): void
    {
        $this->preferred = $preferred;
    }
}