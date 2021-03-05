<?php

namespace App\DTO;

use App\Entity\Organization;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

class UserDTO
{
    /**
     * @Assert\NotBlank()
     * @Assert\Length(max=32)
     */
    public string $login;

    /**
     * @Assert\NotBlank()
     * @Assert\Length(max=32)
     */
    public string $password;

    /**
     * @Assert\NotBlank()
     */
    public int $age;

    public bool $isActive;

    public array $linkedOrganizations;

    public function __construct(array $data)
    {
        $this->login = $data['login'];
        $this->password = $data['login'] ?? '';
        $this->age = $data['age'] ?? 0;
        $this->isActive = $data['isActive'] ?? false;
        $this->linkedOrganizations = $data['linkedOrganizations'] ?? [];
    }

    public static function fromEntity(User $user): self
    {
        return new self([
            'login' => $user->getLogin(),
            'password' => $user->getPassword(),
            'age' => $user->getAge(),
            'isActive' => $user->isActive(),
            'linkedOrganizations' => array_map(
                static function (Organization $organization) {
                    return ['id' => $organization->getId(), 'name' => $organization->getName()];
                },
                $user->getLinkedOrganizations()->getValues()
            )
        ]);
    }

    /**
     * @return OrganizationDTO[]
     */
    public function getLinkedOrganizationDTOs(): array
    {
        return array_map(
            static function (array $organizationData) {
                return new OrganizationDTO($organizationData);
            },
            $this->linkedOrganizations
        );
    }
}