<?php
declare(strict_types=1);

namespace App\DTO;

use App\Entity\User;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class SaveUserDTO
{
    /** @var string */
    private $login;

    /** @var string */
    private $phone;

    /** @var string */
    private $email;

    /** @var bool */
    private $preferEmail;

    public function __construct(string $login, string $phone, string $email, bool $preferEmail)
    {
        $this->login = $login;
        $this->phone = $phone;
        $this->email = $email;
        $this->preferEmail = $preferEmail;
    }


    public function toEntity(User $user): User
    {
        $user->setLogin($this->login);
        $user->setPhone($this->phone);
        $user->setEmail($this->email);
        $user->setPreferred($this->preferEmail ? User::EMAIL_NOTIFICATION : User::SMS_NOTIFICATION);

        return $user;
    }
}
