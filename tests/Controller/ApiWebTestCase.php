<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiWebTestCase extends WebTestCase
{
    protected static ?string $adminToken = null;

    protected function createAuthenticatedClient(string $username = 'admin_test', string $password = 'admin123'): KernelBrowser
    {
        $client = static::createClient();

        if (self::$adminToken === null) {
            $this->ensureAdminUserExists($client, $username, $password);
            self::$adminToken = $this->getJwtToken($client, $username, $password);
        }

        $client->setServerParameter('HTTP_Authorization', 'Bearer ' . self::$adminToken);

        return $client;
    }

    private function ensureAdminUserExists(KernelBrowser $client, string $username, string $password): void
    {
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $existing = $em->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existing !== null) {
            return;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_ADMIN']);
        $em->persist($user);
        $em->flush();
    }

    private function getJwtToken(KernelBrowser $client, string $username, string $password): string
    {
        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => $password,
        ]));

        self::assertResponseIsSuccessful('Login should succeed');
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $data, 'Response should contain token');

        return $data['token'];
    }

    public static function tearDownAfterClass(): void
    {
        self::$adminToken = null;
        parent::tearDownAfterClass();
    }
}
