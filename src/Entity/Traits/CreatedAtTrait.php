<?php
declare(strict_types=1);

namespace App\Entity\Traits;

use DateTime;
use Doctrine\ORM\Mapping;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 *
 * @Mapping\HasLifecycleCallbacks
 */
trait CreatedAtTrait
{
    /**
     * @var DateTime
     *
     * @Mapping\Column(name="created_at", type="datetime", nullable=false)
     */
    protected $createdAt;

    public function getCreatedAt(): DateTime {
        return $this->createdAt;
    }

    /**
     * @Mapping\PrePersist
     */
    public function setCreatedAt(): void {
        $this->createdAt = new DateTime();
    }
}