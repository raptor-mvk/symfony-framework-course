<?php

namespace App\Controller\Api\SaveUser\v4\Output;

use App\Entity\Traits\SafeLoadFieldsTrait;
use Symfony\Component\Validator\Constraints as Assert;

class UserIsSavedDTO
{
    use SafeLoadFieldsTrait;

    /**
     * @Assert\NotBlank()
     * @Assert\Type("numeric")
     */
    public int $id;

    /**
     * @Assert\NotBlank()
     * @Assert\Type("string")
     * @Assert\Length(max=32)
     */
    public string $login;

    /**
     * @Assert\NotBlank()
     * @Assert\Type("numeric")
     */
    public int $age;

    /**
     * @Assert\NotBlank()
     * @Assert\Type("bool")
     */
    public bool $isActive;

    public function getSafeFields(): array
    {
        return ['id', 'login', 'age', 'isActive'];
    }
}
