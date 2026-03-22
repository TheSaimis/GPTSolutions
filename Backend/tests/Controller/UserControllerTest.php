<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserControllerTest extends ApiWebTestCase
{
    public function testCreateUserSuccess(): void
    {
        $client = $this->createAuthenticatedClient();

        $username = 'newuser_' . uniqid();

        $client->request('POST', '/api/users/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => 'password123',
            'role' => 'ROLE_USER',
        ]));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('SUCCESS', $data['status']);
        self::assertSame($username, $data['data']['username']);
        self::assertContains('ROLE_USER', $data['data']['role']);
    }

    public function testCreateUserInvalidJson(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/users/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'not json');

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateUserShortUsername(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/users/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'ab',
            'password' => 'password123',
            'role' => 'ROLE_USER',
        ]));

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertStringContainsString('3', $data['error'] ?? '');
    }

    public function testCreateUserShortPassword(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/users/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'validuser',
            'password' => '12345',
            'role' => 'ROLE_USER',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateUserInvalidRole(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/users/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'user_' . uniqid(),
            'password' => 'password123',
            'role' => 'ROLE_INVALID',
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateUserDuplicateUsername(): void
    {
        $client = $this->createAuthenticatedClient();
        $username = 'dupuser_' . uniqid();

        $client->request('POST', '/api/users/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => 'password123',
            'role' => 'ROLE_USER',
        ]));
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/users/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => 'password123',
            'role' => 'ROLE_USER',
        ]));

        self::assertResponseStatusCodeSame(409);
    }

    public function testGetAllUsers(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/users/all');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($data);
    }

    public function testGetOneUserNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/users/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetOneUserSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $user = new User();
        $user->setUsername('getuser_' . uniqid());
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();
        $id = $user->getId();
        self::assertNotNull($id);

        $client->request('GET', "/api/users/{$id}");

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($id, $data['id']);
    }

    public function testUpdateUserSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $user = new User();
        $user->setUsername('upduser_' . uniqid());
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();
        $id = $user->getId();
        self::assertNotNull($id);

        $client->request('POST', "/api/users/{$id}/update", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'updated_' . uniqid(),
        ]));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('SUCCESS', $data['status']);
    }

    public function testDeleteUserSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $user = new User();
        $user->setUsername('deluser_' . uniqid());
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();
        $id = $user->getId();
        self::assertNotNull($id);

        $client->request('POST', "/api/users/{$id}/delete");

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('SUCCESS', $data['status']);
    }

    public function testDeleteUserNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/users/999999/delete');

        self::assertResponseStatusCodeSame(404);
    }
}
