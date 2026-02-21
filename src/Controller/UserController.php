<?php
namespace App\Controller;

use App\Entity\User;
use App\Entity\ObjektoDarbuotojai;
use App\Repository\UserRepository;
use App\Repository\ObjektoDarbuotojaiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// ============================
// USER CONTROLLER FOR ADMIN
// THESE API'S ARE NOT FOR REGULAR USERS
// ============================

final class UserController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/users/create', name: 'app_user_create', methods: ['POST'])]
    public function createUser(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $user = new User();
        
        // empty not allowed
        if (empty($data['username'])) {
            return new JsonResponse(['error' => 'Invalid username'], 400);
        }
        if (empty($data['password'])) {
            return new JsonResponse(['error' => 'Invalid password'], 400);
        }
        if (!isset($data['role']) || !in_array($data['role'], ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_VIEWER', 'ROLE_MANAGER'])) {
            return new JsonResponse(['error' => 'Invalid role'], 400);
        }

        // not empty allowed :)
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setUsername($data['username']);
        $user->setPassword($hashedPassword);
        $user->setRoles([$data['role']]);

        // insert stuff to database
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Vartotojas sukurtas',
            'data' => [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'role' => $user->getRoles(),
        ]
        ]);
        die;
    }

    #[Route('/users/all', name: 'app_user_all', methods: ['GET'])]
    public function getAllUsers(UserRepository $repo): JsonResponse
    {
        $users = $repo->findAll();
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'role' => $user->getRoles(),
            ];
        }
        return new JsonResponse($data);
    }
}

