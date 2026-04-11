<?php
namespace App\Controller;

use App\Entity\Category;
use App\Entity\CompanyRequisite;
use App\Entity\CompanyType;
use App\Repository\CompanyRequisiteRepository;
use App\Repository\CompanyTypeRepository;
use App\Services\AuditLogger;
use App\Services\ManagerGenderResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/company')]
final class CompanyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private ManagerGenderResolver $genderResolver,
        private AuditLogger $auditLogger,
        private CompanyTypeRepository $companyTypeRepository,
    ) {}

    #[Route('/create', name: 'api_company_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Neteisingas JSON'], 400);
        }

        $company = new CompanyRequisite();
        $company->setCompanyName($data['companyName'] ?? '');
        $company->setCompanyNameEn($data['companyNameEn'] ?? null);
        $company->setCompanyNameRu($data['companyNameRu'] ?? null);
        $company->setCode($data['code'] ?? '');
        $company->setCompanyTypeRef($this->resolveCompanyTypeFromPayload($data));
        $company->setAddress($data['address'] ?? null);
        $company->setAddressEn($data['addressEn'] ?? null);
        $company->setAddressRu($data['addressRu'] ?? null);
        $company->setCityOrDistrict($data['cityOrDistrict'] ?? null);
        $company->setCityOrDistrictEn($data['cityOrDistrictEn'] ?? null);
        $company->setCityOrDistrictRu($data['cityOrDistrictRu'] ?? null);
        $managerType = $data['managerType'] ?? null;
        $company->setManagerType($managerType);
        $company->setManagerGender($data['managerGender'] ?? null);
        $company->setManagerFirstName($data['managerFirstName'] ?? null);
        $company->setManagerFirstNameEn($data['managerFirstNameEn'] ?? null);
        $company->setManagerFirstNameRu($data['managerFirstNameRu'] ?? null);
        $company->setManagerLastName($data['managerLastName'] ?? null);
        $company->setManagerLastNameEn($data['managerLastNameEn'] ?? null);
        $company->setManagerLastNameRu($data['managerLastNameRu'] ?? null);
        $company->setDocumentDate($data['documentDate'] ?? null);
        if (array_key_exists('aapKortelesPagrindas', $data)) {
            $company->setAapKortelesPagrindas($this->normalizeAapKortelesPagrindasPayload($data['aapKortelesPagrindas']));
        }
        $company->setRole($data['role'] ?? null);
        $company->setRoleEn($data['roleEn'] ?? null);
        $company->setRoleRu($data['roleRu'] ?? null);
        $categoryId = (int) ($data['categoryId'] ?? $data['catagoryId'] ?? 0);
        if ($categoryId > 0) {
            $category = $this->em->getRepository(Category::class)->find($categoryId);
            if (! $category instanceof Category) {
                return new JsonResponse([
                    'status' => 'FAIL',
                    'errors' => ['categoryId' => ['Kategorija nerasta.']],
                ], 400);
            }
            $company->setCompanyCategory($category);
        } else {
            $company->setCompanyCategory(null);
        }
        $company->setDirectory(
            trim((string) ($data['directory'] ?? '')) !== ''
                ? trim((string) $data['directory'])
                : $this->buildCompanyDirectory(
                $company->getCompanyCategory()?->getName() ?? '',
                $company->getCompanyType() ?? $data['companyType'] ?? '',
                $company->getCompanyName() ?? '',
                $company->getCode() ?? ''
            )
        );
        $company->setCreatedAt(new \DateTimeImmutable());

        $repo = $this->em->getRepository(CompanyRequisite::class);
        if ($repo->existsByName($company->getCompanyName() ?? '')) {
            return new JsonResponse([
                'status' => 'FAIL',
                'errors' => ['companyName' => ['Įmonė su tokiu pavadinimu jau egzistuoja.']],
            ], 400);
        }

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
        $company = $repo->find($id);
        if (! $company) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Įmonė nerasta'], 404);
        }

        return new JsonResponse($this->toArray($company));
    }

    #[Route('/update/{id}', name: 'api_company_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, CompanyRequisiteRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $company = $repo->find($id);
        if (! $company) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Įmonė nerasta'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Neteisingas JSON'], 400);
        }

        if (isset($data['companyName'])) {
            $newName = trim((string) $data['companyName']);
            $repo    = $this->em->getRepository(CompanyRequisite::class);
            if ($repo->existsByName($newName, $id)) {
                return new JsonResponse([
                    'status' => 'FAIL',
                    'errors' => ['companyName' => ['Įmonė su tokiu pavadinimu jau egzistuoja.']],
                ], 400);
            }
            $company->setCompanyName($newName);
        }
        if (array_key_exists('companyNameEn', $data)) {
            $company->setCompanyNameEn($data['companyNameEn']);
        }
        if (array_key_exists('companyNameRu', $data)) {
            $company->setCompanyNameRu($data['companyNameRu']);
        }

        if (isset($data['code'])) {
            $company->setCode($data['code']);
        }

        if (array_key_exists('companyTypeId', $data) || array_key_exists('company_type_id', $data)) {
            $tid = (int) ($data['companyTypeId'] ?? $data['company_type_id'] ?? 0);
            if ($tid > 0) {
                $t = $this->companyTypeRepository->find($tid);
                $company->setCompanyTypeRef($t instanceof CompanyType ? $t : null);
            } else {
                $company->setCompanyTypeRef(null);
            }
        } elseif (array_key_exists('companyType', $data)) {
            $short = $data['companyType'];
            if ($short === null || trim((string) $short) === '') {
                $company->setCompanyTypeRef(null);
            } else {
                $company->setCompanyTypeRef(
                    $this->companyTypeRepository->findOneByTypeShortLoose((string) $short)
                );
            }
        }

        if (array_key_exists('address', $data)) {
            $company->setAddress($data['address']);
        }
        if (array_key_exists('addressEn', $data)) {
            $company->setAddressEn($data['addressEn']);
        }
        if (array_key_exists('addressRu', $data)) {
            $company->setAddressRu($data['addressRu']);
        }

        if (array_key_exists('cityOrDistrict', $data)) {
            $company->setCityOrDistrict($data['cityOrDistrict']);
        }
        if (array_key_exists('cityOrDistrictEn', $data)) {
            $company->setCityOrDistrictEn($data['cityOrDistrictEn']);
        }
        if (array_key_exists('cityOrDistrictRu', $data)) {
            $company->setCityOrDistrictRu($data['cityOrDistrictRu']);
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

        if (array_key_exists('managerFirstNameEn', $data)) {
            $company->setManagerFirstNameEn($data['managerFirstNameEn']);
        }

        if (array_key_exists('managerFirstNameRu', $data)) {
            $company->setManagerFirstNameRu($data['managerFirstNameRu']);
        }

        if (array_key_exists('managerLastName', $data)) {
            $company->setManagerLastName($data['managerLastName']);
        }
        if (array_key_exists('managerLastNameEn', $data)) {
            $company->setManagerLastNameEn($data['managerLastNameEn']);
        }
        if (array_key_exists('managerLastNameRu', $data)) {
            $company->setManagerLastNameRu($data['managerLastNameRu']);
        }

        if (array_key_exists('documentDate', $data)) {
            $company->setDocumentDate($data['documentDate']);
        }

        if (array_key_exists('aapKortelesPagrindas', $data)) {
            $company->setAapKortelesPagrindas($this->normalizeAapKortelesPagrindasPayload($data['aapKortelesPagrindas']));
        }

        if (array_key_exists('role', $data)) {
            $company->setRole($data['role']);
        }

        if (array_key_exists('roleEn', $data)) {
            $company->setRoleEn($data['roleEn']);
        }

        if (array_key_exists('roleRu', $data)) {
            $company->setRoleRu($data['roleRu']);
        }

        if (array_key_exists('categoryId', $data) || array_key_exists('catagoryId', $data)) {
            $categoryId = (int) ($data['categoryId'] ?? $data['catagoryId'] ?? 0);
            if ($categoryId > 0) {
                $category = $this->em->getRepository(Category::class)->find($categoryId);
                if (! $category instanceof Category) {
                    return new JsonResponse([
                        'status' => 'FAIL',
                        'errors' => ['categoryId' => ['Kategorija nerasta.']],
                    ], 400);
                }
                $company->setCompanyCategory($category);
            } else {
                $company->setCompanyCategory(null);
            }
        }

        $directoryFromPathParts = array_key_exists('companyName', $data)
            || array_key_exists('companyType', $data)
            || array_key_exists('companyTypeId', $data)
            || array_key_exists('company_type_id', $data)
            || array_key_exists('categoryId', $data)
            || array_key_exists('catagoryId', $data)
            || array_key_exists('code', $data);

        if ($directoryFromPathParts) {
            $company->setDirectory($this->buildCompanyDirectory(
                $company->getCompanyCategory()?->getName() ?? '',
                $company->getCompanyType() ?? '',
                $company->getCompanyName() ?? '',
                $company->getCode() ?? ''
            ));
        } elseif (array_key_exists('directory', $data)) {
            $company->setDirectory(trim((string) $data['directory']) !== '' ? trim((string) $data['directory']) : null);
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

        return new JsonResponse([
            'status' => 'SUCCESS',
            'data'   => $this->toArray($company),
        ], Response::HTTP_OK);
    }

    #[Route('/delete/{id}', name: 'api_company_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, CompanyRequisiteRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $company = $repo->find($id);
        if (! $company) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Įmonė nerasta'], 404);
        }

        $company->setDeleted(true);
        $company->setDeletedDate(new \DateTimeImmutable());
        $this->em->flush();

        $this->auditLogger->log("Įmonė \"{$company->getCompanyName()}\" (ID: {$id}) pažymėta ištrinimui");

        return new JsonResponse([
            'status' => 'SUCCESS',
            'data'   => [
                'id'          => $company->getId(),
                'companyName' => $company->getCompanyName(),
                'code'        => $company->getCode(),
                'email'       => $company->getEmail(),
                'phone'       => $company->getPhone(),
                'deleted'     => $company->isDeleted(),
                'deletedDate' => $company->getDeletedDate()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    #[Route('/all/deleted', name: 'api_company_all_deleted', methods: ['GET'])]
    public function getAllDeleted(CompanyRequisiteRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $companies = $repo->findBy(['deleted' => true]);
        $result    = [];
        foreach ($companies as $company) {
            $result[] = $this->toArray($company);
        }
        return new JsonResponse($result);
    }

    /** @param array<string, mixed> $data */
    private function resolveCompanyTypeFromPayload(array $data): ?CompanyType
    {
        $tid = (int) ($data['companyTypeId'] ?? $data['company_type_id'] ?? 0);
        if ($tid > 0) {
            $t = $this->companyTypeRepository->find($tid);

            return $t instanceof CompanyType ? $t : null;
        }

        if (isset($data['companyType']) && trim((string) $data['companyType']) !== '') {
            return $this->companyTypeRepository->findOneByTypeShortLoose((string) $data['companyType']);
        }

        return null;
    }

    private function companyTypeToArray(?CompanyType $t): ?array
    {
        if ($t === null) {
            return null;
        }

        return [
            'id'          => $t->getId(),
            'typeShort'   => $t->getTypeShort(),
            'typeShortEn' => $t->getTypeShortEn(),
            'typeShortRu' => $t->getTypeShortRu(),
            'type'        => $t->getType(),
            'typeEn'      => $t->getTypeEn(),
            'typeRu'      => $t->getTypeRu(),
        ];
    }

    private function normalizeAapKortelesPagrindasPayload(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function sanitizeForFilename(string $name): string
    {
        $s = trim($name);
        $s = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/', '_', trim($s)) ?? $s;
        return $s !== '' ? $s : '';
    }

    /** Grąžina kelią: {kategorija}/{tipas}/{pavadinimas}. */
    private function buildCompanyDirectory(string $categoryName, string $tipas, string $companyName, string $code): string
    {
        $categorySlug = $this->sanitizeForFilename($categoryName) ?: 'be_kategorijos';
        $tipasSlug   = $this->sanitizeForFilename($tipas) ?: 'Kita';
        $companySlug = $this->sanitizeForFilename($companyName) ?: $code;
        return $categorySlug . '/' . $tipasSlug . '/' . $companySlug;
    }

    private function toArray(CompanyRequisite $c): array
    {
        return [
            'id'               => $c->getId(),
            'companyName'      => $c->getCompanyName(),
            'companyNameEn'    => $c->getCompanyNameEn(),
            'companyNameRu'    => $c->getCompanyNameRu(),
            'code'             => $c->getCode(),
            'companyType'      => $c->getCompanyType(),
            'companyTypeId'    => $c->getCompanyTypeRef()?->getId(),
            'companyTypeRow'   => $this->companyTypeToArray($c->getCompanyTypeRef()),
            'address'          => $c->getAddress(),
            'addressEn'        => $c->getAddressEn(),
            'addressRu'        => $c->getAddressRu(),
            'cityOrDistrict'   => $c->getCityOrDistrict(),
            'cityOrDistrictEn' => $c->getCityOrDistrictEn(),
            'cityOrDistrictRu' => $c->getCityOrDistrictRu(),
            'managerType'      => $c->getManagerType(),
            'managerGender'    => $c->getManagerGender(),
            'managerFirstName'   => $c->getManagerFirstName(),
            'managerFirstNameEn' => $c->getManagerFirstNameEn(),
            'managerFirstNameRu' => $c->getManagerFirstNameRu(),
            'managerLastName'    => $c->getManagerLastName(),
            'managerLastNameEn'  => $c->getManagerLastNameEn(),
            'managerLastNameRu'  => $c->getManagerLastNameRu(),
            'documentDate'          => $c->getDocumentDate(),
            'aapKortelesPagrindas' => $c->getAapKortelesPagrindas(),
            'role'               => $c->getRole(),
            'roleEn'             => $c->getRoleEn(),
            'roleRu'             => $c->getRoleRu(),
            'categoryId'       => $c->getCompanyCategory()?->getId(),
            'categoryName'     => $c->getCompanyCategory()?->getName(),
            'directory'        => $c->getDirectory(),
            'createdAt'        => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
            'modifiedAt'       => $c->getModifiedAt()?->format('Y-m-d H:i:s'),
            'deleted'          => $c->isDeleted(),
            'deletedDate'      => $c->getDeletedDate()?->format('Y-m-d H:i:s'),
        ];
    }
}
