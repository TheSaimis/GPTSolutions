<?php
namespace App\Controller;

use App\Entity\CompanyRequisite;
use App\Repository\CompanyRequisiteRepository;
use App\Services\AuditLogger;
use App\Services\ManagerGenderResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Response;

#[Route('/api/company')]
final class CompanyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private ManagerGenderResolver $genderResolver,
        private AuditLogger $auditLogger,
    ) {}

    #[Route('/create', name: 'api_company_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid JSON'], 400);
        }

        $company = new CompanyRequisite();
        $company->setCompanyName($data['companyName'] ?? '');
        $company->setCode($data['code'] ?? '');
        $company->setCompanyType($data['companyType'] ?? null);
        $company->setAddress($data['address'] ?? null);
        $company->setCityOrDistrict($data['cityOrDistrict'] ?? null);
        $managerType = $data['managerType'] ?? null;
        $company->setManagerType($managerType);
        $company->setManagerGender($data['managerGender'] ?? null);
        $company->setManagerFirstName($data['managerFirstName'] ?? null);
        $company->setManagerLastName($data['managerLastName'] ?? null);
        $company->setDocumentDate($data['documentDate'] ?? null);
        $company->setRole($data['role'] ?? null);
        $company->setDirectory(
            trim((string) ($data['directory'] ?? '')) !== ''
                ? trim((string) $data['directory'])
                : $this->buildCompanyDirectory(
                $company->getCompanyType() ?? $data['companyType'] ?? '',
                $company->getCompanyName() ?? '',
                $company->getCode() ?? ''
            )
        );
        $company->setCreatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($company);
        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $violation) {
                $messages[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return new JsonResponse(['status' => 'FAIL', 'errors' => $messages], 400);
        }

        $this->em->persist($company);
        $this->em->flush();

        $this->auditLogger->log("Sukurta įmonė \"{$company->getCompanyName()}\" (ID: {$company->getId()})");

        return new JsonResponse(
            $this->toArray($company), 201);
    }

    #[Route('/all', name: 'api_company_all', methods: ['GET'])]
    public function getAll(CompanyRequisiteRepository $repo): JsonResponse
    {
        $companies = $repo->findBy(['deleted' => false]);
        $result    = [];
        foreach ($companies as $company) {
            $result[] = $this->toArray($company);
        }

        return new JsonResponse($result);
    }

    #[Route('/companies', name: 'api_company_public', methods: ['GET'])]
    public function getCompanies(CompanyRequisiteRepository $repo): JsonResponse
    {
        $companies = $repo->findBy(['deleted' => false]);
        $result    = [];
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
        if (! $company) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Company not found'], 404);
        }

        return new JsonResponse($this->toArray($company));
    }

    #[Route('/update/{id}', name: 'api_company_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, CompanyRequisiteRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $company = $repo->find($id);
        if (! $company) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Company not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Invalid JSON'], 400);
        }

        if (isset($data['companyName'])) {
            $company->setCompanyName($data['companyName']);
        }

        if (isset($data['code'])) {
            $company->setCode($data['code']);
        }

        if (array_key_exists('email', $data)) {
            $company->setEmail($data['email']);
        }

        if (array_key_exists('companyType', $data)) {
            $company->setCompanyType($data['companyType']);
        }

        if (array_key_exists('address', $data)) {
            $company->setAddress($data['address']);
        }

        if (array_key_exists('cityOrDistrict', $data)) {
            $company->setCityOrDistrict($data['cityOrDistrict']);
        }

        if (array_key_exists('managerType', $data)) {
            $managerType = $data['managerType'];
            $company->setManagerType($managerType);
            $company->setManagerGender(
                $managerType !== null && trim((string) $managerType) !== ''
                    ? $this->genderResolver->resolve((string) $managerType)
                    : null
            );
        }
        if (array_key_exists('managerFirstName', $data)) {
            $company->setManagerFirstName($data['managerFirstName']);
        }

        if (array_key_exists('managerLastName', $data)) {
            $company->setManagerLastName($data['managerLastName']);
        }

        if (array_key_exists('documentDate', $data)) {
            $company->setDocumentDate($data['documentDate']);
        }

        if (array_key_exists('role', $data)) {
            $company->setRole($data['role']);
        }

        if (array_key_exists('directory', $data)) {
            $company->setDirectory(trim((string) $data['directory']) !== '' ? $data['directory'] : null);
        } elseif (isset($data['companyName']) || array_key_exists('companyType', $data)) {
            $company->setDirectory($this->buildCompanyDirectory(
                $company->getCompanyType() ?? '',
                $company->getCompanyName() ?? '',
                $company->getCode() ?? ''
            ));
        }
        $errors = $this->validator->validate($company);
        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $violation) {
                $messages[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return new JsonResponse(['status' => 'FAIL', 'errors' => $messages], 400);
        }

        $this->em->flush();

        $this->auditLogger->log("Atnaujinta įmonė \"{$company->getCompanyName()}\" (ID: {$id})");

        return $this->json([
            'status'  => 'SUCCESS',
            'data' => $company,
        ], Response::HTTP_OK);
    }

    #[Route('/delete/{id}', name: 'api_company_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, CompanyRequisiteRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $company = $repo->find($id);
        if (! $company) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Company not found'], 404);
        }

        $company->setDeleted(true);
        $company->setDeletedDate(new \DateTimeImmutable());
        $this->em->flush();

        $this->auditLogger->log("Įmonė \"{$company->getCompanyName()}\" (ID: {$id}) pažymėta ištrinimui");

        return new JsonResponse(['status' => 'SUCCESS']);
    }

    private function sanitizeForFilename(string $name): string
    {
        $s = trim($name);
        $s = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/', '_', trim($s)) ?? $s;
        return $s !== '' ? $s : '';
    }

    /** Grąžina kelią: {tipas}/{pavadinimas} (pvz. UAB/UAB_Test_Company) */
    private function buildCompanyDirectory(string $tipas, string $companyName, string $code): string
    {
        $tipasSlug   = $this->sanitizeForFilename($tipas) ?: 'Kita';
        $companySlug = $this->sanitizeForFilename($companyName) ?: $code;
        return $tipasSlug . '/' . $companySlug;
    }

    private function toArray(CompanyRequisite $c): array
    {
        return [
            'id'               => $c->getId(),
            'companyName'      => $c->getCompanyName(),
            'code'             => $c->getCode(),
            'companyType'      => $c->getCompanyType(),
            'address'          => $c->getAddress(),
            'cityOrDistrict'   => $c->getCityOrDistrict(),
            'managerType'      => $c->getManagerType(),
            'managerGender'    => $c->getManagerGender(),
            'managerFirstName' => $c->getManagerFirstName(),
            'managerLastName'  => $c->getManagerLastName(),
            'documentDate'     => $c->getDocumentDate(),
            'role'             => $c->getRole(),
            'directory'        => $c->getDirectory(),
            'createdAt'        => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
            'modifiedAt'       => $c->getModifiedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
