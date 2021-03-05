<?php


namespace App\Controller\Api\v1;

use App\DTO\UserDTO;
use App\Entity\User;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/** @Route("/api/v1/user") */
class UserController
{
    /** @var UserService */
    private $userService;

    /** @var Environment */
    private $twig;

    public function __construct(UserService $userService, Environment $twig)
    {
        $this->userService = $userService;
        $this->twig = $twig;
    }

    /**
     * @Route("/form", methods={"POST"})
     */
    public function saveUserAction(Request $request): Response
    {
        $form = $this->userService->getSaveForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userId = $this->userService->saveUser(new User(), new UserDTO($form->getData()));
            [$data, $code] = ($userId === null) ? [['success' => false], 400] : [['id' => $userId], 200];

            return new JsonResponse($data, $code);
        }

        $content = $this->twig->render('form.twig', [
            'form' => $form->createView(),
        ]);

        return new Response($content);
    }

    /**
     * @Route("/form", methods={"GET"})
     */
    public function getSaveFormAction(): Response
    {
        $form = $this->userService->getSaveForm();
        $content = $this->twig->render('form.twig', [
            'form' => $form->createView(),
        ]);

        return new Response($content);
    }

    /**
     * @Route("", methods={"GET"})
     */
    public function getUsersAction(Request $request): Response
    {
        $perPage = $request->request->get('perPage');
        $page = $request->request->get('page');
        $users = $this->userService->getUsers($page ?? 0, $perPage ?? 20);
        $code = empty($users) ? 204 : 200;

        return new JsonResponse(['users' => $users], $code);
    }

    /**
     * @Route("", methods={"DELETE"})
     */
    public function deleteUserAction(Request $request): Response
    {
        $userId = $request->query->get('userId');
        $result = $this->userService->deleteUserById($userId);

        return new JsonResponse(['success' => $result], $result ? 200 : 404);
    }

    /**
     * @Route("/form/{id}", methods={"GET"}, requirements={"id":"\d+"})
     */
    public function getUpdateFormAction(int $id): Response
    {
        $form = $this->userService->getUpdateForm($id);

        if ($form === null) {
            return new JsonResponse(['message' => "User with ID $id not found"], 404);
        }
        $content = $this->twig->render('form.twig', [
            'form' => $form->createView(),
        ]);

        return new Response($content);
    }

    /**
     * @Route("/form/{id}", methods={"PATCH"}, requirements={"id":"\d+"})
     */
    public function updateUserAction(Request $request, int $id): Response
    {
        $form = $this->userService->getUpdateForm($id);
        if ($form === null) {
            return new JsonResponse(['message' => "User with ID $id not found"], 404);
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->userService->updateUser($id, $form->getData());

            return new JsonResponse(['success' => $result], $result ? 200 : 400);
        }
        $content = $this->twig->render('form.twig', [
            'form' => $form->createView(),
        ]);

        return new Response($content);
    }
}