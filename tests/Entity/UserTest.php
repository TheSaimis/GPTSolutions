<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('hashed_password');
        $user->setFirstName('Jonas');
        $user->setLastName('Jonaitis');
        $user->setEmail('jonas@example.com');
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $this->assertSame('testuser', $user->getUsername());
        $this->assertSame('hashed_password', $user->getPassword());
        $this->assertSame('Jonas', $user->getFirstName());
        $this->assertSame('Jonaitis', $user->getLastName());
        $this->assertSame('jonas@example.com', $user->getEmail());
        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
        $this->assertNull($user->getId());
    }

    public function testGetUserIdentifierReturnsUsername(): void
    {
        $user = new User();
        $user->setUsername('admin');

        $this->assertSame('admin', $user->getUserIdentifier());
    }

    public function testGetRolesReturnsUniqueRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']);

        $roles = $user->getRoles();
        $this->assertCount(2, $roles);
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setPassword('secret');

        $user->eraseCredentials();

        $this->assertSame('test', $user->getUsername());
        $this->assertSame('secret', $user->getPassword());
    }

    public function testNullableOptionalFields(): void
    {
        $user = new User();
        $user->setUsername('minimal');
        $user->setPassword('pw');

        $this->assertNull($user->getFirstName());
        $this->assertNull($user->getLastName());
        $this->assertNull($user->getEmail());
    }
}
