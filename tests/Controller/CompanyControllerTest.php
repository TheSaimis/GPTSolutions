<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CompanyRequisite;
use Doctrine\ORM\EntityManagerInterface;

final class CompanyControllerTest extends ApiWebTestCase
{
    public function testCreateCompanySuccess(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/company/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'company_name' => 'Test UAB',
            'code' => '123456789',
            'email' => 'test@example.com',
        ]));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('SUCCESS', $data['status']);
        self::assertArrayHasKey('data', $data);
        self::assertSame('Test UAB', $data['data']['companyName']);
        self::assertSame('123456789', $data['data']['code']);
        self::assertSame('test@example.com', $data['data']['email']);
    }

    public function testCreateCompanyInvalidJson(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/company/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json');

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FAIL', $data['status']);
        self::assertArrayHasKey('error', $data);
    }

    public function testCreateCompanyValidationFails(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/company/create', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'company_name' => '',
            'code' => '',
        ]));

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FAIL', $data['status']);
        self::assertArrayHasKey('errors', $data);
    }

    public function testGetAllCompanies(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/company/all');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($data);
    }

    public function testGetCompaniesPublic(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/company/companies');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($data);
    }

    public function testGetOneCompanyNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/company/999999');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('FAIL', $data['status']);
        self::assertSame('Company not found', $data['error']);
    }

    public function testGetOneCompanySuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $company = new CompanyRequisite();
        $company->setCompanyName('Get Test UAB');
        $company->setCode('111111111');
        $company->setEmail('get@test.lt');
        $company->setCreatedAt(new \DateTimeImmutable());
        $em->persist($company);
        $em->flush();
        $id = $company->getId();
        self::assertNotNull($id);

        $client->request('GET', "/api/company/{$id}");

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Get Test UAB', $data['companyName']);
        self::assertSame('111111111', $data['code']);
    }

    public function testUpdateCompanySuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $company = new CompanyRequisite();
        $company->setCompanyName('Update Test UAB');
        $company->setCode('222222222');
        $company->setEmail('update@test.lt');
        $company->setCreatedAt(new \DateTimeImmutable());
        $em->persist($company);
        $em->flush();
        $id = $company->getId();
        self::assertNotNull($id);

        $client->request('POST', "/api/company/update/{$id}", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'companyName' => 'Updated Name UAB',
        ]));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('SUCCESS', $data['status']);
    }

    public function testUpdateCompanyNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/company/update/999999', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['companyName' => 'X']));

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteCompanySuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $company = new CompanyRequisite();
        $company->setCompanyName('Delete Test UAB');
        $company->setCode('333333333');
        $company->setEmail('delete@test.lt');
        $company->setCreatedAt(new \DateTimeImmutable());
        $em->persist($company);
        $em->flush();
        $id = $company->getId();
        self::assertNotNull($id);

        $client->request('POST', "/api/company/delete/{$id}");

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('SUCCESS', $data['status']);
    }

    public function testDeleteCompanyNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/company/delete/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnauthenticatedRequestFails(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/company/all');

        self::assertResponseStatusCodeSame(401);
    }
}
