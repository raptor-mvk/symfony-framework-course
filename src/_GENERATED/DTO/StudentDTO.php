<?php

namespace Generated\DTO;

use JMS\Serializer\Annotation as JMS;

class StudentDTO
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

    /**
     * @var int
     * @JMS\Type("int")
     */
    private $teacherId;

    public function __construct($entity)
    {
        $this->name = $entity->getName();
        $this->surname = $entity->getSurname();
        $this->patronymic = $entity->getPatronymic();
        $this->teacherId = $entity->getTeacher()->getId();
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

    public function getTeacherId(): int
    {
        return $this->teacherId;
    }
}
