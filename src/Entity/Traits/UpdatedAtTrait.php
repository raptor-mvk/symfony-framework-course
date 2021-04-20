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
trait UpdatedAtTrait
{
    /**
     * @var DateTime
     *
     * @Mapping\Column(name="updated_at", type="datetime", nullable=true)
     */
    protected $updatedAt;

    public function getUpdatedAt(): DateTime {
        return $this->updatedAt;
    }

    /**
     * @Mapping\PreUpdate
     * @Mapping\PrePersist
     */
    public function updateUpdatedAt(): void {
        $this->updatedAt = new DateTime();
    }
}