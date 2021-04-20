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
 * @Mapping\Table(name="email_notification")
 * @Mapping\Entity
 * @Mapping\HasLifecycleCallbacks
 */
class EmailNotification
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
     * @var string
     *
     * @Mapping\Column(type="string", length=128, nullable=false)
     */
    private $email;

    /**
     * @var string
     *
     * @Mapping\Column(type="string", length=512, nullable=false)
     */
    private $text;

    /**
     * @return int
     */
    public function getId()
    {
        return (int)$this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
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
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @param string $text
     */
    public function setText(string $text): void
    {
        $this->text = $text;
    }
}