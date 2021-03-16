<?php

namespace App\DTO;

use App\Entity\User;
use JsonException;
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

    public array $roles;

    public ?int $age;

    public ?bool $isActive;

    /**
     * @throws JsonException
     */
    public function __construct(array $data)
    {
        $this->login = $data['login'] ?? '';
        $this->password = $data['password'] ?? '';
        $this->roles = json_decode($data['roles'] ?? '{}', true, 512, JSON_THROW_ON_ERROR) ?? [];
        $this->age = $data['age'] ?? null;
        $this->isActive = $data['isActive'] ?? null;
    }

    /**
     * @throws JsonException
     */
    public static function fromEntity(User $user): self
    {
        return new self([
            'login' => $user->getLogin(),
            'password' => $user->getPassword(),
            'roles' => $user->getRoles(),
            'age' => $user->getAge(),
            'isActive' => $user->isActive(),
        ]);
    }
}
