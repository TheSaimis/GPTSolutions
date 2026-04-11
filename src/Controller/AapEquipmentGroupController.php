<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AapEquipmentGroup;
use App\Entity\AapEquipmentGroupEquipment;
use App\Entity\AapEquipmentGroupWorker;
use App\Entity\CompanyRequisite;
use App\Entity\CompanyWorker;
use App\Entity\Equipment;
use App\Entity\Worker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/aap-equipment-groups')]
final class AapEquipmentGroupController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'aap_equipment_groups_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $companyIdParam = $request->query->get('companyId');
        if (! is_numeric((string) $companyIdParam) || (int) $companyIdParam <= 0) {
            return $this->json(['message' => 'Būtinas užklausos parametras companyId'], 400);
        }

        $company = $this->em->getRepository(CompanyRequisite::class)->find((int) $companyIdParam);
        if (! $company instanceof CompanyRequisite) {
            return $this->json(['message' => 'Įmonė nerasta'], 404);
        }

        $groups = $this->em->getRepository(AapEquipmentGroup::class)->findBy(
            ['companyRequisite' => $company],
            ['sortOrder' => 'ASC', 'id' => 'ASC']
        );

        return $this->json(array_map(fn (AapEquipmentGroup $g) => $this->serializeGroup($g), $groups));
    }

    #[Route('', name: 'aap_equipment_groups_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        $companyId = isset($payload['companyId']) ? (int) $payload['companyId'] : 0;
        $name = trim((string) ($payload['name'] ?? ''));
        if ($companyId <= 0 || $name === '') {
            return $this->json(['message' => 'Būtini laukai companyId ir name'], 400);
        }

        $company = $this->em->getRepository(CompanyRequisite::class)->find($companyId);
        if (! $company instanceof CompanyRequisite) {
            return $this->json(['message' => 'Įmonė nerasta'], 404);
        }

        $group = new AapEquipmentGroup();
        $group->setCompanyRequisite($company);
        $group->setName($name);
        $group->setSortOrder((int) ($payload['sortOrder'] ?? 0));
        $this->em->persist($group);
        $this->em->flush();

        return $this->json($this->serializeGroup($group), 201);
    }

    #[Route('/{id}', name: 'aap_equipment_groups_update', methods: ['PATCH', 'PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->em->getRepository(AapEquipmentGroup::class)->find($id);
        if (! $group instanceof AapEquipmentGroup) {
            return $this->json(['message' => 'Nerasta'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return $this->json(['message' => 'Pavadinimas negali būti tuščias'], 400);
            }
            $group->setName($name);
        }
        if (isset($payload['sortOrder'])) {
            $group->setSortOrder((int) $payload['sortOrder']);
        }

        $this->em->flush();

        return $this->json($this->serializeGroup($group));
    }

    #[Route('/{id}', name: 'aap_equipment_groups_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->em->getRepository(AapEquipmentGroup::class)->find($id);
        if (! $group instanceof AapEquipmentGroup) {
            return $this->json(['message' => 'Nerasta'], 404);
        }

        $this->em->remove($group);
        $this->em->flush();

        return $this->json(['message' => 'SUCCESS']);
    }

    #[Route('/{id}/workers', name: 'aap_equipment_groups_add_worker', methods: ['POST'])]
    public function addWorker(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->em->getRepository(AapEquipmentGroup::class)->find($id);
        if (! $group instanceof AapEquipmentGroup) {
            return $this->json(['message' => 'Grupė nerasta'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }
        $workerId = isset($payload['workerId']) ? (int) $payload['workerId'] : 0;
        if ($workerId <= 0) {
            return $this->json(['message' => 'Būtinas laukas workerId'], 400);
        }

        $worker = $this->em->getRepository(Worker::class)->find($workerId);
        if (! $worker instanceof Worker) {
            return $this->json(['message' => 'Darbuotojo tipas nerastas'], 404);
        }

        $company = $group->getCompanyRequisite();
        if (! $company instanceof CompanyRequisite) {
            return $this->json(['message' => 'Netinkama grupė'], 500);
        }

        if (! $this->isWorkerOnCompany($company, $worker)) {
            return $this->json(['message' => 'Šis darbuotojų tipas nepriskirtas šiai įmonei'], 400);
        }

        $existing = $this->em->getRepository(AapEquipmentGroupWorker::class)->findOneBy([
            'equipmentGroup' => $group,
            'worker' => $worker,
        ]);
        if ($existing instanceof AapEquipmentGroupWorker) {
            return $this->json(['message' => 'Jau yra grupėje'], 409);
        }

        $link = new AapEquipmentGroupWorker();
        $link->setEquipmentGroup($group);
        $link->setWorker($worker);
        $this->em->persist($link);
        $this->em->flush();

        return $this->json($this->serializeGroup($group), 201);
    }

    #[Route('/{groupId}/workers/{workerId}', name: 'aap_equipment_groups_remove_worker', methods: ['DELETE'])]
    public function removeWorker(int $groupId, int $workerId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->em->getRepository(AapEquipmentGroup::class)->find($groupId);
        if (! $group instanceof AapEquipmentGroup) {
            return $this->json(['message' => 'Grupė nerasta'], 404);
        }

        $worker = $this->em->getRepository(Worker::class)->find($workerId);
        if (! $worker instanceof Worker) {
            return $this->json(['message' => 'Darbuotojo tipas nerastas'], 404);
        }

        $link = $this->em->getRepository(AapEquipmentGroupWorker::class)->findOneBy([
            'equipmentGroup' => $group,
            'worker' => $worker,
        ]);
        if (! $link instanceof AapEquipmentGroupWorker) {
            return $this->json(['message' => 'Nerasta'], 404);
        }

        $this->em->remove($link);
        $this->em->flush();

        return $this->json($this->serializeGroup($group));
    }

    #[Route('/{id}/equipment', name: 'aap_equipment_groups_add_equipment', methods: ['POST'])]
    public function addEquipment(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->em->getRepository(AapEquipmentGroup::class)->find($id);
        if (! $group instanceof AapEquipmentGroup) {
            return $this->json(['message' => 'Grupė nerasta'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }
        $equipmentId = isset($payload['equipmentId']) ? (int) $payload['equipmentId'] : 0;
        if ($equipmentId <= 0) {
            return $this->json(['message' => 'Būtinas laukas equipmentId'], 400);
        }

        $equipment = $this->em->getRepository(Equipment::class)->find($equipmentId);
        if (! $equipment instanceof Equipment) {
            return $this->json(['message' => 'Priemonė nerasta'], 404);
        }

        $existing = $this->em->getRepository(AapEquipmentGroupEquipment::class)->findOneBy([
            'equipmentGroup' => $group,
            'equipment' => $equipment,
        ]);
        if ($existing instanceof AapEquipmentGroupEquipment) {
            return $this->json(['message' => 'Jau yra grupėje'], 409);
        }

        $link = new AapEquipmentGroupEquipment();
        $link->setEquipmentGroup($group);
        $link->setEquipment($equipment);
        if (array_key_exists('quantity', $payload)) {
            $link->setQuantity(Equipment::normalizeDocumentQuantity($payload['quantity']));
        }
        $this->em->persist($link);
        $this->em->flush();

        return $this->json($this->serializeGroup($group), 201);
    }

    #[Route('/{groupId}/equipment/{equipmentId}', name: 'aap_equipment_groups_patch_equipment', methods: ['PATCH'])]
    public function patchEquipment(int $groupId, int $equipmentId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->em->getRepository(AapEquipmentGroup::class)->find($groupId);
        if (! $group instanceof AapEquipmentGroup) {
            return $this->json(['message' => 'Grupė nerasta'], 404);
        }

        $equipment = $this->em->getRepository(Equipment::class)->find($equipmentId);
        if (! $equipment instanceof Equipment) {
            return $this->json(['message' => 'Priemonė nerasta'], 404);
        }

        $link = $this->em->getRepository(AapEquipmentGroupEquipment::class)->findOneBy([
            'equipmentGroup' => $group,
            'equipment' => $equipment,
        ]);
        if (! $link instanceof AapEquipmentGroupEquipment) {
            return $this->json(['message' => 'Nerasta'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return $this->json(['message' => 'Neteisingas užklausos JSON'], 400);
        }

        if (array_key_exists('quantity', $payload)) {
            $link->setQuantity(Equipment::normalizeDocumentQuantity($payload['quantity']));
        }

        $this->em->flush();

        return $this->json($this->serializeGroup($group));
    }

    #[Route('/{groupId}/equipment/{equipmentId}', name: 'aap_equipment_groups_remove_equipment', methods: ['DELETE'])]
    public function removeEquipment(int $groupId, int $equipmentId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->em->getRepository(AapEquipmentGroup::class)->find($groupId);
        if (! $group instanceof AapEquipmentGroup) {
            return $this->json(['message' => 'Grupė nerasta'], 404);
        }

        $equipment = $this->em->getRepository(Equipment::class)->find($equipmentId);
        if (! $equipment instanceof Equipment) {
            return $this->json(['message' => 'Priemonė nerasta'], 404);
        }

        $link = $this->em->getRepository(AapEquipmentGroupEquipment::class)->findOneBy([
            'equipmentGroup' => $group,
            'equipment' => $equipment,
        ]);
        if (! $link instanceof AapEquipmentGroupEquipment) {
            return $this->json(['message' => 'Nerasta'], 404);
        }

        $this->em->remove($link);
        $this->em->flush();

        return $this->json($this->serializeGroup($group));
    }

    private function isWorkerOnCompany(CompanyRequisite $company, Worker $worker): bool
    {
        $cw = $this->em->getRepository(CompanyWorker::class)->findOneBy([
            'companyRequisite' => $company,
            'worker' => $worker,
        ]);

        return $cw instanceof CompanyWorker;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGroup(AapEquipmentGroup $group): array
    {
        $workers = [];
        $gwList = $group->getGroupWorkers()->toArray();
        usort(
            $gwList,
            static fn (AapEquipmentGroupWorker $a, AapEquipmentGroupWorker $b): int =>
                strcmp($a->getWorker()?->getName() ?? '', $b->getWorker()?->getName() ?? '')
        );
        foreach ($gwList as $gw) {
            $w = $gw->getWorker();
            if ($w === null || $w->getId() === null) {
                continue;
            }
            $workers[] = [
                'id' => $gw->getId(),
                'worker' => ['id' => $w->getId(), 'name' => $w->getName()],
            ];
        }

        $equipment = [];
        $geList = $group->getGroupEquipment()->toArray();
        usort(
            $geList,
            static fn (AapEquipmentGroupEquipment $a, AapEquipmentGroupEquipment $b): int =>
                strcmp($a->getEquipment()?->getName() ?? '', $b->getEquipment()?->getName() ?? '')
        );
        foreach ($geList as $ge) {
            $e = $ge->getEquipment();
            if ($e === null || $e->getId() === null) {
                continue;
            }
            $equipment[] = [
                'id' => $ge->getId(),
                'quantity' => $ge->getQuantity(),
                'equipment' => [
                    'id' => $e->getId(),
                    'name' => $e->getName(),
                    'expirationDate' => $e->getExpirationDate(),
                    'unitOfMeasurement' => $e->getUnitOfMeasurement(),
                ],
            ];
        }

        return [
            'id' => $group->getId(),
            'companyId' => $group->getCompanyRequisite()?->getId(),
            'name' => $group->getName(),
            'sortOrder' => $group->getSortOrder(),
            'workers' => $workers,
            'equipment' => $equipment,
        ];
    }
}
