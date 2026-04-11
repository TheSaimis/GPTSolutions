<?php

declare(strict_types=1);

namespace App\Controller\AAP\BodyPart;

use App\Entity\BodyPart;
use App\Entity\BodyPartCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/body-parts')]
final class BodyPartController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'body_part_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->em->getRepository(BodyPart::class)
            ->createQueryBuilder('bp')
            ->leftJoin('bp.category', 'c')
            ->addSelect('c')
            ->addOrderBy('c.lineNumber', 'ASC')
            ->addOrderBy('bp.lineNumber', 'ASC')
            ->addOrderBy('bp.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (BodyPart $item): array => [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'lineNumber' => $item->getLineNumber(),
                'category' => $item->getCategory() !== null ? [
                    'id' => $item->getCategory()?->getId(),
                    'name' => $item->getCategory()?->getName(),
                    'lineNumber' => $item->getCategory()?->getLineNumber(),
                ] : null,
            ],
            $items
        );

        return $this->json($data);
    }

    #[Route('/{id}', name: 'body_part_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(BodyPart::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'Kūno dalis nerasta'], 404);
        }

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
            'category' => $item->getCategory() !== null ? [
                'id' => $item->getCategory()?->getId(),
                'name' => $item->getCategory()?->getName(),
                'lineNumber' => $item->getCategory()?->getLineNumber(),
            ] : null,
        ]);
    }

    #[Route('', name: 'body_part_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $lineNumber = (int) ($payload['lineNumber'] ?? 0);
        $categoryId = $payload['categoryId'] ?? null;

        if ($name === '') {
            return $this->json(['message' => 'Būtinas laukas „name“'], 400);
        }

        if ($categoryId === null) {
            return $this->json(['message' => 'Būtinas laukas „categoryId“'], 400);
        }

        $category = $this->em->getRepository(BodyPartCategory::class)->find((int) $categoryId);
        if ($category === null) {
            return $this->json(['message' => 'Kūno dalių kategorija nerasta'], 404);
        }

        $item = new BodyPart();
        $item->setName($name);
        $item->setLineNumber($lineNumber);
        $item->setCategory($category);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'lineNumber' => $category->getLineNumber(),
            ],
        ], 201);
    }

    #[Route('/{id}', name: 'body_part_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(BodyPart::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'Kūno dalis nerasta'], 404);
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

        if (array_key_exists('categoryId', $payload)) {
            $category = $this->em->getRepository(BodyPartCategory::class)->find((int) $payload['categoryId']);
            if ($category === null) {
                return $this->json(['message' => 'Kūno dalių kategorija nerasta'], 404);
            }
            $item->setCategory($category);
        }

        $this->em->flush();

        $category = $item->getCategory();

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'lineNumber' => $item->getLineNumber(),
            'category' => $category !== null ? [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'lineNumber' => $category->getLineNumber(),
            ] : null,
        ]);
    }

    #[Route('/{id}', name: 'body_part_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(BodyPart::class)->find($id);
        if ($item === null) {
            return $this->json(['message' => 'Kūno dalis nerasta'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }
}