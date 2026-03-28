<?php

declare(strict_types=1);

namespace App\Controller\HealthCertificate;

use App\Entity\HealthRiskFactor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/health-certificate/risk-factors')]
final class HealthRiskFactorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'health_certificate_risk_factor_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->em->getRepository(HealthRiskFactor::class)
            ->createQueryBuilder('f')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (HealthRiskFactor $item): array => self::serializeItem($item),
            $items
        );

        return $this->json($data);
    }

    #[Route('/{id}', name: 'health_certificate_risk_factor_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $item = $this->em->getRepository(HealthRiskFactor::class)->find($id);
        if (! $item instanceof HealthRiskFactor) {
            return $this->json(['message' => 'Risk factor not found'], 404);
        }

        return $this->json(self::serializeItem($item));
    }

    #[Route('', name: 'health_certificate_risk_factor_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $code = trim((string) ($payload['code'] ?? $payload['cipher'] ?? ''));
        $lineNumber = (int) ($payload['lineNumber'] ?? 0);

        if ($name === '' || $code === '') {
            return $this->json(['message' => 'Fields "name" and "code/cipher" are required'], 400);
        }

        $item = new HealthRiskFactor();
        $item->setName($name);
        $item->setCode($code);
        $item->setLineNumber($lineNumber);

        $this->em->persist($item);
        $this->em->flush();

        return $this->json(self::serializeItem($item), 201);
    }

    #[Route('/{id}', name: 'health_certificate_risk_factor_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->em->getRepository(HealthRiskFactor::class)->find($id);
        if (! $item instanceof HealthRiskFactor) {
            return $this->json(['message' => 'Risk factor not found'], 404);
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

        if (array_key_exists('code', $payload) || array_key_exists('cipher', $payload)) {
            $code = trim((string) ($payload['code'] ?? $payload['cipher']));
            if ($code === '') {
                return $this->json(['message' => 'Field "code/cipher" cannot be empty'], 400);
            }
            $item->setCode($code);
        }

        if (array_key_exists('lineNumber', $payload)) {
            $item->setLineNumber((int) $payload['lineNumber']);
        }

        $this->em->flush();

        return $this->json(self::serializeItem($item));
    }

    #[Route('/{id}', name: 'health_certificate_risk_factor_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->em->getRepository(HealthRiskFactor::class)->find($id);
        if (! $item instanceof HealthRiskFactor) {
            return $this->json(['message' => 'Risk factor not found'], 404);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }

    private static function serializeItem(HealthRiskFactor $item): array
    {
        return [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'code' => $item->getCode(),
            'cipher' => $item->getCode(),
            'lineNumber' => $item->getLineNumber(),
        ];
    }
}
