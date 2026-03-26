<?php

declare (strict_types = 1);

namespace App\Controller\AAP\Risk;

use App\Entity\RiskCategory;
use App\Entity\RiskGroup;
use App\Entity\RiskSubcategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/risk-subcategories')]
final class RiskSubcategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'risk_subcategory_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->em->getRepository(RiskSubcategory::class)
            ->createQueryBuilder('rs')
            ->leftJoin('rs.category', 'rc')
            ->leftJoin('rs.group', 'rg')
            ->addSelect('rc', 'rg')
            ->addOrderBy('rg.lineNumber', 'ASC')
            ->addOrderBy('rc.lineNumber', 'ASC')
            ->addOrderBy('rs.lineNumber', 'ASC')
            ->addOrderBy('rs.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            fn(RiskSubcategory $item): array=> $this->serializeRiskSubcategory($item),
            $items
        );
        return $this->json($data);
    }

    #[Route('/{id}', name: 'risk_subcategory_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(RiskSubcategory::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'RiskSubcategory not found'], 404);
        }

        return $this->json($this->serializeRiskSubcategory($item));
    }

    #[Route('', name: 'risk_subcategory_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $name       = trim((string) ($payload['name'] ?? ''));
        $lineNumber = (int) ($payload['lineNumber'] ?? 0);
        $categoryId = $payload['categoryId'] ?? null;
        $groupId    = $payload['groupId'] ?? null;

        if ($name === '') {
            return $this->json(['message' => 'Field "name" is required'], 400);
        }

        if ($categoryId === null && $groupId === null) {
            return $this->json(['message' => 'Either "categoryId" or "groupId" is required'], 400);
        }

        if ($categoryId !== null && $groupId !== null) {
            return $this->json(['message' => 'Provide only one of: "categoryId" or "groupId"'], 400);
        }

        $item = new RiskSubcategory();
        $item->setName($name);
        $item->setLineNumber($lineNumber);

        if ($categoryId !== null) {
            $category = $this->em->getRepository(RiskCategory::class)->find((int) $categoryId);
            if ($category === null) {
                return $this->json(['message' => 'RiskCategory not found'], 404);
            }
            $item->setCategory($category);
            $item->setGroup(null);
        }

        if ($groupId !== null) {
            $group = $this->em->getRepository(RiskGroup::class)->find((int) $groupId);
            if ($group === null) {
                return $this->json(['message' => 'RiskGroup not found'], 404);
            }
            $item->setGroup($group);
            $item->setCategory(null);
        }

        $this->em->persist($item);
        $this->em->flush();

        return $this->json($this->serializeRiskSubcategory($item), 201);
    }

    #[Route('/{id}', name: 'risk_subcategory_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(RiskSubcategory::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'RiskSubcategory not found'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return $this->json(['message' => 'Field "name" cannot be empty'], 400);
            }
            $item->setName($name);
        }

        if (array_key_exists('lineNumber', $payload)) {
            $item->setLineNumber((int) $payload['lineNumber']);
        }

        $hasCategoryId = array_key_exists('categoryId', $payload);
        $hasGroupId    = array_key_exists('groupId', $payload);

        if ($hasCategoryId && $hasGroupId) {
            $categoryId = $payload['categoryId'];
            $groupId    = $payload['groupId'];

            if ($categoryId !== null && $groupId !== null) {
                return $this->json(['message' => 'Provide only one of: "categoryId" or "groupId"'], 400);
            }

            if ($categoryId === null && $groupId === null) {
                $item->setCategory(null);
                $item->setGroup(null);
            } elseif ($categoryId !== null) {
                $category = $this->em->getRepository(RiskCategory::class)->find((int) $categoryId);
                if ($category === null) {
                    return $this->json(['message' => 'RiskCategory not found'], 404);
                }
                $item->setCategory($category);
                $item->setGroup(null);
            } else {
                $group = $this->em->getRepository(RiskGroup::class)->find((int) $groupId);
                if ($group === null) {
                    return $this->json(['message' => 'RiskGroup not found'], 404);
                }
                $item->setGroup($group);
                $item->setCategory(null);
            }
        } elseif ($hasCategoryId) {
            $categoryId = $payload['categoryId'];

            if ($categoryId === null) {
                $item->setCategory(null);
            } else {
                $category = $this->em->getRepository(RiskCategory::class)->find((int) $categoryId);
                if ($category === null) {
                    return $this->json(['message' => 'RiskCategory not found'], 404);
                }
                $item->setCategory($category);
                $item->setGroup(null);
            }
        } elseif ($hasGroupId) {
            $groupId = $payload['groupId'];

            if ($groupId === null) {
                $item->setGroup(null);
            } else {
                $group = $this->em->getRepository(RiskGroup::class)->find((int) $groupId);
                if ($group === null) {
                    return $this->json(['message' => 'RiskGroup not found'], 404);
                }
                $item->setGroup($group);
                $item->setCategory(null);
            }
        }

        if ($item->getCategory() === null && $item->getGroup() === null) {
            return $this->json(['message' => 'RiskSubcategory must belong either to category or directly to group'], 400);
        }

        $this->em->flush();

        return $this->json($this->serializeRiskSubcategory($item));
    }

    #[Route('/{id}', name: 'risk_subcategory_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(RiskSubcategory::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'RiskSubcategory not found'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }

    private function serializeRiskSubcategory(RiskSubcategory $item): array
    {
        $category       = $item->getCategory();
        $group          = $item->getGroup();
        $effectiveGroup = $item->getEffectiveGroup();

        return [
            'id'             => $item->getId(),
            'name'           => $item->getName(),
            'lineNumber'     => $item->getLineNumber(),
            'category'       => $category !== null ? [
                'id'         => $category->getId(),
                'name'       => $category->getName(),
                'lineNumber' => $category->getLineNumber(),
            ] : null,
            'group'          => $group !== null ? [
                'id'         => $group->getId(),
                'name'       => $group->getName(),
                'lineNumber' => $group->getLineNumber(),
            ] : null,
            'effectiveGroup' => $effectiveGroup !== null ? [
                'id'         => $effectiveGroup->getId(),
                'name'       => $effectiveGroup->getName(),
                'lineNumber' => $effectiveGroup->getLineNumber(),
            ] : null,
        ];
    }
}
