<?php

namespace App\DTO;

use App\Entity\Organization;
use Symfony\Component\Validator\Constraints as Assert;

class OrganizationDTO
{
    /**
     * @Assert\NotBlank()
     * @Assert\Length(max=32)
     */
    public string $name;

    public int $id;

    /**
     * OrganizationDTO constructor.
     * @param string $name
     */
    public function __construct(array $data)
    {
        $this->name = $data['name'] ?? '';
        $this->id = $data['id'] ?? 0;
    }

    public static function fromEntity(Organization $organization)
    {
        return new self(['id' => $organization->getId(), 'name' => $organization->getName()]);
    }
}