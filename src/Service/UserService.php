<?php

namespace App\Service;

use App\DTO\UserDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Symfony\Forms\UserOrganizationType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Collection;

class UserService
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /**
     * @var FormInterface
     */
    private $formFactory;

    /**
     * @var OrganizationService
     */
    private OrganizationService $organizationService;

    public function __construct(EntityManagerInterface $entityManager, FormFactoryInterface $formFactory, OrganizationService $organizationService)
    {
        $this->entityManager = $entityManager;
        $this->formFactory = $formFactory;
        $this->organizationService = $organizationService;
    }
    public function getSaveForm(): FormInterface
    {
        return $this->formFactory->createBuilder(FormType::class)
            ->add('login', TextType::class)
            ->add('password', PasswordType::class, ['required' => false])
            ->add('age', IntegerType::class)
            ->add('isActive', CheckboxType::class, ['required' => false])
            ->add('linkedOrganizations', CollectionType::class, [
                'entry_type' => UserOrganizationType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
            ])
            ->add('submit', SubmitType::class)
            ->getForm();
    }

    public function saveUser(User $user, UserDTO $userDTO): ?int
    {
        $user->setLogin($userDTO->login);
        $user->setPassword($userDTO->password);
        $user->setAge($userDTO->age);
        $user->setIsActive($userDTO->isActive);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user->getId();
    }

    public function getUpdateForm(int $userId): ?FormInterface
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User $user */
        $user = $userRepository->find($userId);
        if ($user === null) {
            return null;
        }

        return $this->formFactory->createBuilder(FormType::class, UserDTO::fromEntity($user))
            ->add('login', TextType::class)
            ->add('password', PasswordType::class, ['required' => false])
            ->add('age', IntegerType::class)
            ->add('isActive', CheckboxType::class, ['required' => false])
            ->add('submit', SubmitType::class)
            ->add('linkedOrganizations', CollectionType::class, [
                'entry_type' => UserOrganizationType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
            ])
            ->setMethod('PATCH')
            ->getForm();
    }

    public function updateUser(int $userId, UserDTO $userDTO): bool
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User $user */
        $user = $userRepository->find($userId);

        if ($user === null) {
            return false;
        }

        $user->setLinkedOrganizations(new ArrayCollection());

        foreach ($userDTO->getLinkedOrganizationDTOs() as $organization)
        {
            if ($organization->id > 0) {
                $this->organizationService->updateOrganization($organization->id, $organization->name);
                $organizationId = $organization->id;
            } else {
                $organizationId = $this->organizationService->saveOrganization($organization->name);
            }
            $user->addLinkedOrganization($this->organizationService->getById($organizationId));
        }

        return $this->saveUser($user, $userDTO);
    }

    public function deleteUserById(int $userId): bool
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User $user */
        $user = $userRepository->find($userId);
        if ($user === null) {
            return false;
        }
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return User[]
     */
    public function getUsers(int $page, int $perPage): array
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);

        return $userRepository->getUsers($page, $perPage);
    }
}