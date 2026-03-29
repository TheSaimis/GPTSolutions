<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Worker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/workers')]
final class WorkerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'worker_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->em->getRepository(Worker::class)
            ->createQueryBuilder('w')
            ->addOrderBy('w.name', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (Worker $item): array => self::serializeWorker($item),
            $items
        );

        return $this->json($data);
    }

    #[Route('/{id}', name: 'worker_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(Worker::class)->find($id);
        if (! $item instanceof Worker) {
            return $this->json(['message' => 'Worker not found'], 404);
        }

        return $this->json(self::serializeWorker($item));
    }

    #[Route('', name: 'worker_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->json(['message' => 'Field "name" is required'], 400);
        }

        $item = new Worker();
        $item->setName($name);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json(self::serializeWorker($item), 201);
    }

    #[Route('/{id}', name: 'worker_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(Worker::class)->find($id);
        if (! $item instanceof Worker) {
            return $this->json(['message' => 'Worker not found'], 404);
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

        $this->em->flush();

        return $this->json(self::serializeWorker($item));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', name: 'worker_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(Worker::class)->find($id);
        if (! $item instanceof Worker) {
            return $this->json(['message' => 'Worker not found'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }

    private static function serializeWorker(Worker $item): array
    {
        return [
            'id' => $item->getId(),
            'name' => $item->getName(),
        ];
    }
}
