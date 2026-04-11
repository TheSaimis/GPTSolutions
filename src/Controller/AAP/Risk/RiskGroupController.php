<?php

declare(strict_types=1);

namespace App\Controller\AAP\Risk;

use App\Entity\RiskGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/risk-groups')]
final class RiskGroupController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'risk_group_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->em->getRepository(RiskGroup::class)
            ->createQueryBuilder('rg')
            ->addOrderBy('rg.lineNumber', 'ASC')
            ->addOrderBy('rg.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (RiskGroup $item): array => [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'lineNumber' => $item->getLineNumber(),
            ],
            $items
        );

        return $this->json($data);
    }

    #[Route('/{id}', name: 'risk_group_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(RiskGroup::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'Rizikos grupė nerasta'], 404);
        }

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
            'categories' => array_map(
                static fn ($category): array => [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'lineNumber' => $category->getLineNumber(),
                ],
                $item->getCategories()->toArray()
            ),
            'directSubcategories' => array_map(
                static fn ($sub): array => [
                    'id' => $sub->getId(),
                    'name' => $sub->getName(),
                    'lineNumber' => $sub->getLineNumber(),
                ],
                $item->getDirectSubcategories()->toArray()
            ),
        ]);
    }

    #[Route('', name: 'risk_group_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $lineNumber = (int) ($payload['lineNumber'] ?? 0);

        if ($name === '') {
            return $this->json(['message' => 'Būtinas laukas „name“'], 400);
        }

        $item = new RiskGroup();
        $item->setName($name);
        $item->setLineNumber($lineNumber);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
        ], 201);
    }

    #[Route('/{id}', name: 'risk_group_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(RiskGroup::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'Rizikos grupė nerasta'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return $this->json(['message' => 'Laukas „name“ negali būti tuščias'], 400);
            }
            $item->setName($name);
        }

        if (array_key_exists('lineNumber', $payload)) {
            $item->setLineNumber((int) $payload['lineNumber']);
        }

        $this->em->flush();

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
        ]);
    }

    #[Route('/{id}', name: 'risk_group_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(RiskGroup::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'Rizikos grupė nerasta'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }
}