<?php

declare (strict_types = 1);

namespace App\Controller;

use App\Services\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class DeleteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route('/api/delete/{id}/{item}', name: 'api_soft_delete', methods: ['DELETE', 'POST'], requirements: ['id' => '\d+'])]
    public function softDelete(int $id, string $item): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $now = new \DateTimeImmutable();

        switch ($item) {
            case 'user':
                $entity = $this->em->getRepository(\App\Entity\User::class)->find($id);
                if (! $entity) {
                    return new JsonResponse(['status' => 'FAIL', 'error' => 'Naudotojas nerastas'], 404);
                }
                $entity->setDeleted(true);
                $entity->setDeletedDate($now);
                $this->em->flush();
                $this->auditLogger->log("Naudotojas (ID: {$id}) pažymėtas ištrinimui");
                break;

            case 'company':
                $entity = $this->em->getRepository(\App\Entity\CompanyRequisite::class)->find($id);
                if (! $entity) {
                    return new JsonResponse(['status' => 'FAIL', 'error' => 'Įmonė nerasta'], 404);
                }
                $entity->setDeleted(true);
                $entity->setDeletedDate($now);
                $this->em->flush();
                $this->auditLogger->log("Įmonė \"{$entity->getCompanyName()}\" (ID: {$id}) pažymėta ištrinimui");
                break;

            default:
                return new JsonResponse(['status' => 'FAIL', 'error' => 'Neleistinas tipas. Galimi: user, company'], 400);
        }

        return new JsonResponse(['status' => 'SUCCESS']);
    }

    #[Route('/api/restore/{id}/{item}', name: 'api_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(int $id, string $item): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        switch ($item) {
            case 'user':
                $entity = $this->em->getRepository(\App\Entity\User::class)->find($id);
                if (! $entity) {
                    return new JsonResponse(['status' => 'FAIL', 'error' => 'Naudotojas nerastas'], 404);
                }
                $entity->setDeleted(false);
                $entity->setDeletedDate(null);
                $this->em->flush();
                $this->auditLogger->log("Naudotojas (ID: {$id}) pažymėtas atstatymui");
                break;
            case 'company':
                $entity = $this->em->getRepository(\App\Entity\CompanyRequisite::class)->find($id);
                if (! $entity) {
                    return new JsonResponse(['status' => 'FAIL', 'error' => 'Įmonė nerasta'], 404);
                }
                $entity->setDeleted(false);
                $entity->setDeletedDate(null);
                $this->em->flush();
                $this->auditLogger->log("Įmonė \"{$entity->getCompanyName()}\" (ID: {$id}) pažymėta atstatymui");
                break;
            default:
                return new JsonResponse(['status' => 'FAIL', 'error' => 'Neleistinas tipas. Galimi: user, company'], 400);
        }
        return new JsonResponse(['status' => 'SUCCESS']);
    }
}
