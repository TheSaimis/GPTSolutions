<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/audit')]
final class AuditLogController extends AbstractController
{
    public function __construct(
        private readonly AuditLogRepository $repo,
    ) {}

    #[Route('', name: 'api_audit_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $limit  = min((int) ($request->query->get('limit', 50)), 200);
        $offset = max((int) ($request->query->get('offset', 0)), 0);

        $logs = $this->repo->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($logs as $log) {
            $data[] = [
                'id'        => $log->getId(),
                'userId'    => $log->getUserId(),
                'action'    => $log->getAction(),
                'createdAt' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($data);
    }
}
