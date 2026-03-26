<?php

declare(strict_types=1);

namespace App\Controller\AAP\BodyPart;

use App\Entity\BodyPartCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/body-part-categories')]
final class BodyPartCategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'body_part_category_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->em->getRepository(BodyPartCategory::class)
            ->createQueryBuilder('c')
            ->addOrderBy('c.lineNumber', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (BodyPartCategory $item): array => [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'lineNumber' => $item->getLineNumber(),
            ],
            $items
        );

        return $this->json($data);
    }

    #[Route('/{id}', name: 'body_part_category_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(BodyPartCategory::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'BodyPartCategory not found'], 404);
        }

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
            'bodyParts' => array_map(
                static fn ($bp): array => [
                    'id' => $bp->getId(),
                    'name' => $bp->getName(),
                    'lineNumber' => $bp->getLineNumber(),
                ],
                $item->getBodyParts()->toArray()
            ),
        ]);
    }

    #[Route('', name: 'body_part_category_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $lineNumber = (int) ($payload['lineNumber'] ?? 0);

        if ($name === '') {
            return $this->json(['message' => 'Field "name" is required'], 400);
        }

        $item = new BodyPartCategory();
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

    #[Route('/{id}', name: 'body_part_category_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(BodyPartCategory::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'BodyPartCategory not found'], 404);
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

        $this->em->flush();

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
        ]);
    }

    #[Route('/{id}', name: 'body_part_category_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(BodyPartCategory::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'BodyPartCategory not found'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }
}