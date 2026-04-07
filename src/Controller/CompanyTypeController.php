<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CompanyType;
use App\Repository\CompanyTypeRepository;
use App\Services\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/company-types')]
final class CompanyTypeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route('', name: 'api_company_types_all', methods: ['GET'])]
    public function all(CompanyTypeRepository $repo): JsonResponse
    {
        $rows = $repo->findBy([], ['typeShort' => 'ASC']);
        $out  = array_map(fn (CompanyType $t): array => $this->toArray($t), $rows);

        return new JsonResponse($out);
    }

    #[Route('/{id}', name: 'api_company_types_one', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function one(int $id, CompanyTypeRepository $repo): JsonResponse
    {
        $t = $repo->find($id);
        if (! $t instanceof CompanyType) {
            return new JsonResponse(['error' => 'Įmonės tipas nerastas.'], 404);
        }

        return new JsonResponse($this->toArray($t));
    }

    #[Route('', name: 'api_company_types_create', methods: ['POST'])]
    public function create(Request $request, CompanyTypeRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Neteisingas JSON'], 400);
        }

        $typeShort = trim((string) ($data['typeShort'] ?? $data['type_short'] ?? ''));
        $type      = trim((string) ($data['type'] ?? ''));

        if ($typeShort === '' || $type === '') {
            return new JsonResponse(['error' => 'Privalomi laukai: typeShort, type'], 400);
        }

        if ($repo->findOneBy(['typeShort' => $typeShort]) instanceof CompanyType) {
            return new JsonResponse(['error' => 'Toks typeShort jau egzistuoja.'], 400);
        }

        $entity = new CompanyType();
        $entity->setTypeShort($typeShort);
        $entity->setType($type);
        $this->applyLocaleFields($entity, $data, false);

        $this->em->persist($entity);
        $this->em->flush();

        $this->auditLogger->log("Sukurtas įmonės tipas \"{$typeShort}\" (ID: {$entity->getId()})");

        return new JsonResponse($this->toArray($entity), 201);
    }

    #[Route('/{id}', name: 'api_company_types_update', methods: ['PATCH', 'PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request, CompanyTypeRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $t = $repo->find($id);
        if (! $t instanceof CompanyType) {
            return new JsonResponse(['error' => 'Įmonės tipas nerastas.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['error' => 'Neteisingas JSON'], 400);
        }

        if (array_key_exists('typeShort', $data) || array_key_exists('type_short', $data)) {
            $ns = trim((string) ($data['typeShort'] ?? $data['type_short'] ?? ''));
            if ($ns === '') {
                return new JsonResponse(['error' => 'typeShort negali būti tuščias.'], 400);
            }
            $other = $repo->findOneBy(['typeShort' => $ns]);
            if ($other instanceof CompanyType && $other->getId() !== $t->getId()) {
                return new JsonResponse(['error' => 'Toks typeShort jau naudojamas.'], 400);
            }
            $t->setTypeShort($ns);
        }

        if (array_key_exists('type', $data)) {
            $v = trim((string) $data['type']);
            if ($v === '') {
                return new JsonResponse(['error' => 'type negali būti tuščias.'], 400);
            }
            $t->setType($v);
        }

        $this->applyLocaleFields($t, $data, true);

        $this->em->flush();

        $this->auditLogger->log("Atnaujintas įmonės tipas \"{$t->getTypeShort()}\" (ID: {$t->getId()})");

        return new JsonResponse($this->toArray($t));
    }

    #[Route('/{id}', name: 'api_company_types_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, CompanyTypeRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $t = $repo->find($id);
        if (! $t instanceof CompanyType) {
            return new JsonResponse(['error' => 'Įmonės tipas nerastas.'], 404);
        }

        if ($t->getCompanyRequisites()->count() > 0) {
            return new JsonResponse(['error' => 'Negalima ištrinti: yra su šiuo tipu susietų įmonių.'], 409);
        }

        $label = $t->getTypeShort();
        $tid   = (int) $t->getId();
        $this->em->remove($t);
        $this->em->flush();

        $this->auditLogger->log("Ištrintas įmonės tipas \"{$label}\" (ID: {$tid})");

        return new JsonResponse(['status' => 'ok']);
    }

    /** @param array<string, mixed> $data */
    private function applyLocaleFields(CompanyType $e, array $data, bool $patchOnlyPresent): void
    {
        $defs = [
            [['typeShortEn', 'type_short_en'], fn (?string $s): mixed => $e->setTypeShortEn($s)],
            [['typeShortRu', 'type_short_ru'], fn (?string $s): mixed => $e->setTypeShortRu($s)],
            [['typeEn', 'type_en'], fn (?string $s): mixed => $e->setTypeEn($s)],
            [['typeRu', 'type_ru'], fn (?string $s): mixed => $e->setTypeRu($s)],
        ];

        foreach ($defs as [$keys, $setter]) {
            [$k1, $k2] = $keys;
            if ($patchOnlyPresent && ! array_key_exists($k1, $data) && ! array_key_exists($k2, $data)) {
                continue;
            }
            if (! $patchOnlyPresent && ! array_key_exists($k1, $data) && ! array_key_exists($k2, $data)) {
                continue;
            }
            $raw = $data[$k1] ?? $data[$k2] ?? null;
            if ($raw === null && $patchOnlyPresent && (array_key_exists($k1, $data) || array_key_exists($k2, $data))) {
                $setter(null);
                continue;
            }
            if ($raw === null) {
                continue;
            }
            $s = trim((string) $raw);
            $setter($s !== '' ? $s : null);
        }
    }

    /** @return array<string, mixed> */
    private function toArray(CompanyType $t): array
    {
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
}
