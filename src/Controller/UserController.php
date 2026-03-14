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

        if (empty($data['firstName']) || !is_string($data['firstName']) || strlen($data['firstName']) < 0) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Nenustatyas vardas'], 400);
        }

        if (empty($data['lastName']) || !is_string($data['lastName']) || strlen($data['lastName']) < 0) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Nenustatya parardė'], 400);
        }

        if (empty($data['password']) || !is_string($data['password']) || strlen($data['password']) < 6) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Password must be at least 6 characters'], 400);
        }

        $allowedRoles = ['ROLE_USER', 'ROLE_ADMIN'];
        if (!isset($data['role']) || !in_array($data['role'], $allowedRoles, true)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid role'], 400);
        }

        $email = isset($data['email']) ? trim((string) $data['email']) : null;
        if ($email !== null && $email !== '') {
            $existingEmail = $repo->findOneBy(['email' => $email]);
            if ($existingEmail) {
                return new JsonResponse(['status' => 'FAIL', 'error' => 'Email already exists'], 409);
            }
        }

        $user = new User();
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setRoles([$data['role']]);
        $user->setFirstName($data['firstName'] ?? null);
        $user->setLastName($data['lastName'] ?? null);
        $user->setEmail($email ?: null);

        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse([
            'status' => 'SUCCESS',
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
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
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'role' => $user->getRoles(),
            ];
        }
        return new JsonResponse($data);
    }

    #[Route('/users/{id}', name: 'app_user_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getOne(int $id, UserRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $repo->find($id);
        if (!$user) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'User not found'], 404);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'role' => $user->getRoles(),
        ]);
    }

    #[Route('/users/{id}/update', name: 'app_user_update', methods: ['POST', 'PUT'], requirements: ['id' => '\d+'])]
    public function updateUser(int $id, Request $request, UserRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $repo->find($id);
        if (!$user) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid JSON'], 400);
        }

        if (isset($data['email'])) {
            $email = trim((string) $data['email']) ?: null;
            if ($email !== null) {
                $existingEmail = $repo->findOneBy(['email' => $email]);
                if ($existingEmail && $existingEmail->getId() !== $id) {
                    return new JsonResponse(['status' => 'FAIL', 'error' => 'Email already exists'], 409);
                }
            }
            $user->setEmail($email);
        }

        if (isset($data['password']) && is_string($data['password']) && strlen($data['password']) >= 6) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }

        $allowedRoles = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_VIEWER', 'ROLE_MANAGER'];
        if (isset($data['role']) && in_array($data['role'], $allowedRoles, true)) {
            $user->setRoles([$data['role']]);
        }

        if (array_key_exists('firstName', $data)) {
            $user->setFirstName($data['firstName'] !== null && $data['firstName'] !== '' ? trim((string) $data['firstName']) : null);
        }
        if (array_key_exists('lastName', $data)) {
            $user->setLastName($data['lastName'] !== null && $data['lastName'] !== '' ? trim((string) $data['lastName']) : null);
        }

        $this->em->flush();

        return new JsonResponse([
            'status' => 'SUCCESS',
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'role' => $user->getRoles(),
            ],
        ]);
    }

    #[Route('/users/{id}/delete', name: 'app_user_delete', methods: ['POST', 'DELETE'], requirements: ['id' => '\d+'])]
    public function deleteUser(int $id, UserRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $repo->find($id);
        if (!$user) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'User not found'], 404);
        }

        $this->em->remove($user);
        $this->em->flush();

        return new JsonResponse(['status' => 'SUCCESS']);
    }
}
