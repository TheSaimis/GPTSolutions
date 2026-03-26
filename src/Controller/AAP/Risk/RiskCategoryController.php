<?php

declare(strict_types=1);

namespace App\Controller\AAP\Risk;

use App\Entity\RiskCategory;
use App\Entity\RiskGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/risk-categories')]
final class RiskCategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'risk_category_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->em->getRepository(RiskCategory::class)
            ->createQueryBuilder('rc')
            ->leftJoin('rc.group', 'rg')
            ->addSelect('rg')
            ->addOrderBy('rg.lineNumber', 'ASC')
            ->addOrderBy('rc.lineNumber', 'ASC')
            ->addOrderBy('rc.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (RiskCategory $item): array => [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'lineNumber' => $item->getLineNumber(),
                'group' => $item->getGroup() !== null ? [
                    'id' => $item->getGroup()?->getId(),
                    'name' => $item->getGroup()?->getName(),
                    'lineNumber' => $item->getGroup()?->getLineNumber(),
                ] : null,
            ],
            $items
        );

        return $this->json($data);
    }

    #[Route('/{id}', name: 'risk_category_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(RiskCategory::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'RiskCategory not found'], 404);
        }

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
            'group' => $item->getGroup() !== null ? [
                'id' => $item->getGroup()?->getId(),
                'name' => $item->getGroup()?->getName(),
                'lineNumber' => $item->getGroup()?->getLineNumber(),
            ] : null,
            'subcategories' => array_map(
                static fn ($sub): array => [
                    'id' => $sub->getId(),
                    'name' => $sub->getName(),
                    'lineNumber' => $sub->getLineNumber(),
                ],
                $item->getSubcategories()->toArray()
            ),
        ]);
    }

    #[Route('', name: 'risk_category_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $lineNumber = (int) ($payload['lineNumber'] ?? 0);
        $groupId = $payload['groupId'] ?? null;

        if ($name === '') {
            return $this->json(['message' => 'Field "name" is required'], 400);
        }

        if ($groupId === null) {
            return $this->json(['message' => 'Field "groupId" is required'], 400);
        }

        $group = $this->em->getRepository(RiskGroup::class)->find((int) $groupId);
        if ($group === null) {
            return $this->json(['message' => 'RiskGroup not found'], 404);
        }

        $item = new RiskCategory();
        $item->setName($name);
        $item->setLineNumber($lineNumber);
        $item->setGroup($group);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
            'group' => [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'lineNumber' => $group->getLineNumber(),
            ],
        ], 201);
    }

    #[Route('/{id}', name: 'risk_category_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(RiskCategory::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'RiskCategory not found'], 404);
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

        if (array_key_exists('groupId', $payload)) {
            $group = $this->em->getRepository(RiskGroup::class)->find((int) $payload['groupId']);
            if ($group === null) {
                return $this->json(['message' => 'RiskGroup not found'], 404);
            }
            $item->setGroup($group);
        }

        $this->em->flush();

        $group = $item->getGroup();

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
            'group' => $group !== null ? [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'lineNumber' => $group->getLineNumber(),
            ] : null,
        ]);
    }

    #[Route('/{id}', name: 'risk_category_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(RiskCategory::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'RiskCategory not found'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }
}