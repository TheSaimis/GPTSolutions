<?php

namespace App\Controller;

use App\Entity\CompanyRequisite;
use App\Repository\CompanyRequisiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/company')]
final class CompanyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Route('/create', name: 'api_company_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid JSON'], 400);
        }

        if (empty($data['company_name']) || empty($data['code'])) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'companyName and code are required'], 400);
        }

        $company = new CompanyRequisite();
        $company->setCompanyName($data['company_name']);
        $company->setCode($data['code']);
        $company->setCompanyType($data['company_type'] ?? null);
        $company->setCategory($data['category'] ?? null);
        $company->setAddress($data['address'] ?? null);
        $company->setCityOrDistrict($data['cityOrDistrict'] ?? null);
        $company->setManagerType($data['manager_type'] ?? null);
        $company->setManagerFirstName($data['manager_first_name'] ?? null);
        $company->setManagerLastName($data['manager_last_name'] ?? null);
        $company->setDocumentDate($data['documentDate'] ?? null);
        $company->setRole($data['role'] ?? null);
        $company->setDirectory($data['directory'] ?? null);
        $company->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($company);
        $this->em->flush();

        return new JsonResponse([
            'status' => 'SUCCESS',
            'data' => $this->toArray($company),
        ], 201);
    }

    #[Route('/all', name: 'api_company_all', methods: ['GET'])]
    public function getAll(CompanyRequisiteRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $companies = $repo->findAll();
        $result = [];
        foreach ($companies as $company) {
            $result[] = $this->toArray($company);
        }

        return new JsonResponse($result);
    }

    #[Route('/companies', name: 'api_company_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getCompanies(CompanyRequisiteRepository $repo): JsonResponse
    {
        $companies = $repo->findAll();
        $result = [];
        foreach ($companies as $company) {
            $result[] = $this->toArray($company);
        }

        return new JsonResponse($result);
    }

    #[Route('/{id}', name: 'api_company_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getOne(int $id, CompanyRequisiteRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $company = $repo->find($id);
        if (!$company) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Company not found'], 404);
        }

        return new JsonResponse($this->toArray($company));
    }

    #[Route('/update/{id}', name: 'api_company_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, CompanyRequisiteRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $company = $repo->find($id);
        if (!$company) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Company not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid JSON'], 400);
        }

        if (isset($data['companyName']))      $company->setCompanyName($data['companyName']);
        if (isset($data['code']))             $company->setCode($data['code']);
        if (array_key_exists('companyType', $data))      $company->setCompanyType($data['companyType']);
        if (array_key_exists('category', $data))         $company->setCategory($data['category']);
        if (array_key_exists('address', $data))          $company->setAddress($data['address']);
        if (array_key_exists('cityOrDistrict', $data))   $company->setCityOrDistrict($data['cityOrDistrict']);
        if (array_key_exists('managerType', $data))      $company->setManagerType($data['managerType']);
        if (array_key_exists('managerFirstName', $data)) $company->setManagerFirstName($data['managerFirstName']);
        if (array_key_exists('managerLastName', $data))  $company->setManagerLastName($data['managerLastName']);
        if (array_key_exists('documentDate', $data))     $company->setDocumentDate($data['documentDate']);
        if (array_key_exists('role', $data))             $company->setRole($data['role']);
        if (array_key_exists('directory', $data))        $company->setDirectory($data['directory']);

        $this->em->flush();

        return new JsonResponse(['status' => 'SUCCESS']);
    }

    #[Route('/delete/{id}', name: 'api_company_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, CompanyRequisiteRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $company = $repo->find($id);
        if (!$company) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Company not found'], 404);
        }

        $this->em->remove($company);
        $this->em->flush();

        return new JsonResponse(['status' => 'SUCCESS']);
    }

    private function toArray(CompanyRequisite $c): array
    {
        return [
            'id'               => $c->getId(),
            'companyName'      => $c->getCompanyName(),
            'code'             => $c->getCode(),
            'companyType'      => $c->getCompanyType(),
            'category'         => $c->getCategory(),
            'address'          => $c->getAddress(),
            'cityOrDistrict'   => $c->getCityOrDistrict(),
            'managerType'      => $c->getManagerType(),
            'managerFirstName' => $c->getManagerFirstName(),
            'managerLastName'  => $c->getManagerLastName(),
            'documentDate'     => $c->getDocumentDate(),
            'role'             => $c->getRole(),
            'directory'        => $c->getDirectory(),
            'createdAt'        => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
