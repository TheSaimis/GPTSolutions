<?php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/users/create', name: 'app_user_create', methods: ['POST'])]
    public function createUser(Request $request, UserRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid JSON'], 400);
        }

        if (empty($data['username']) || !is_string($data['username']) || strlen(trim($data['username'])) < 3) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Username must be at least 3 characters'], 400);
        }

        if (empty($data['password']) || !is_string($data['password']) || strlen($data['password']) < 6) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Password must be at least 6 characters'], 400);
        }

        $allowedRoles = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_VIEWER', 'ROLE_MANAGER'];
        if (!isset($data['role']) || !in_array($data['role'], $allowedRoles, true)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid role'], 400);
        }

        $existing = $repo->findOneBy(['username' => trim($data['username'])]);
        if ($existing) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Username already exists'], 409);
        }

        $user = new User();
        $user->setUsername(trim($data['username']));
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setRoles([$data['role']]);
        $user->setFirstName($data['firstName'] ?? null);
        $user->setLastName($data['lastName'] ?? null);

        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse([
            'status' => 'SUCCESS',
            'data' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'role' => $user->getRoles(),
            ]
        ], 201);
    }

    #[Route('/users/all', name: 'app_user_all', methods: ['GET'])]
    public function getAllUsers(UserRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $repo->findAll();
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'role' => $user->getRoles(),
            ];
        }
        return new JsonResponse($data);
    }
}
