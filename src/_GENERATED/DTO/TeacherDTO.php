<?php

namespace Generated\DTO;

use JMS\Serializer\Annotation as JMS;

class TeacherDTO
{
    /**
     * @var string
     * @JMS\Type("string")
     */
    private $name;

    /**
     * @var string
     * @JMS\Type("string")
     */
    private $surname;

    /**
     * @var string
     * @JMS\Type("string")
     */
    private $patronymic;

    public function __construct($entity)
    {
        $this->name = $entity->getName();
        $this->surname = $entity->getSurname();
        $this->patronymic = $entity->getPatronymic();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function getPatronymic(): string
    {
        return $this->patronymic;
    }
}
