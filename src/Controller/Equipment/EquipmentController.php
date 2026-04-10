<?php

declare (strict_types = 1);

namespace App\Controller\Equipment;

use App\Entity\Equipment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/equipment')]
final class EquipmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'equipment_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->em->getRepository(Equipment::class)
            ->createQueryBuilder('f')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn(Equipment $item): array=> self::serializeItem($item),
            $items
        );

        return $this->json($data);
    }

    #[Route('/{id}', name: 'equipment_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(Equipment::class)->find($id);
        if (! $item instanceof Equipment) {
            return $this->json(['message' => 'Equipment not found'], 404);
        }
        return $this->json(self::serializeItem($item));
    }

    #[Route('', name: 'equipment_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $expirationDate = trim((string) ($payload['expirationDate'] ?? ''));

        if ($name === '' || $expirationDate === '') {
            return $this->json(['message' => 'Fields "name" and "expirationDate" are required'], 400);
        }

        $unitRaw = (string) ($payload['unitOfMeasurement'] ?? $payload['unit'] ?? 'vnt');

        $item = new Equipment();
        $item->setName($name);
        $item->setExpirationDate($expirationDate);
        $item->setUnitOfMeasurement($unitRaw);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json(self::serializeItem($item), 201);
    }

    #[Route('/{id}', name: 'equipment_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $item = $this->em->getRepository(Equipment::class)->find($id);
        if (! $item instanceof Equipment) {
            return $this->json(['message' => 'Nerasta, tikriausiai nebeegzistuoja'], 404);
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

        if (array_key_exists('expirationDate', $payload)) {
            $expirationDate = trim((string) $payload['expirationDate']);
            if ($expirationDate === '') {
                return $this->json(['message' => 'Field "expirationDate" cannot be empty'], 400);
            }
            $item->setExpirationDate($expirationDate);
        }

        if (array_key_exists('unitOfMeasurement', $payload) || array_key_exists('unit', $payload)) {
            $unitRaw = (string) ($payload['unitOfMeasurement'] ?? $payload['unit'] ?? 'vnt');
            $item->setUnitOfMeasurement($unitRaw);
        }

        $this->em->flush();
        return $this->json(self::serializeItem($item));
    }

    #[Route('/{id}', name: 'equipment_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $item = $this->em->getRepository(Equipment::class)->find($id);
        if (! $item instanceof Equipment) {
            return $this->json(['message' => 'Equipment not found'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }

    private static function serializeItem(Equipment $item): array
    {
        return [
            'id'                 => $item->getId(),
            'name'               => $item->getName(),
            'expirationDate'     => $item->getExpirationDate(),
            'unitOfMeasurement'  => $item->getUnitOfMeasurement(),
        ];
    }
}
