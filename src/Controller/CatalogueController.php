<?php

namespace App\Controller;

use App\Services\CreateCatalogue;
use App\Services\DeleteCatalogue;
use App\Services\UpdateCatalogue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Katalogų (templates) valdymas: create, update, delete.
 * Return: { "status": "SUCCESS" } arba { "status": "FAIL" }
 */
final class CatalogueController extends AbstractController
{
    public function __construct(
        private CreateCatalogue $createCatalogue,
        private UpdateCatalogue $updateCatalogue,
        private DeleteCatalogue $deleteCatalogue,
    ) {}

    /**
     * POST /api/catalogue/template/create
     * Sukuria naują katalogą templates/{directory}/{folderName}.
     * Body: { "directory": "4 Tvarkos", "folderName": "Naujas" }
     */
    #[Route('/api/catalogue/template/create', name: 'api_catalogue_template_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $directory = trim((string) ($data['directory'] ?? ''));
        $folderName = trim((string) ($data['folderName'] ?? ''));

        if ($folderName === '') {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $status = $this->createCatalogue->create($directory, $folderName);

        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    /**
     * POST /api/catalogue/template/update
     * Pervadina katalogą templates/{oldDirectory} į templates/{newDirectory}.
     * Body: { "oldDirectory": "4 Tvarkos/Senas", "newDirectory": "4 Tvarkos/Naujas" }
     */
    #[Route('/api/catalogue/template/update', name: 'api_catalogue_template_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $oldDirectory = trim((string) ($data['oldDirectory'] ?? $data['directory'] ?? ''));
        $newDirectory = trim((string) ($data['newDirectory'] ?? ''));

        if ($oldDirectory === '' || $newDirectory === '') {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $status = $this->updateCatalogue->update($oldDirectory, $newDirectory);

        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }

    /**
     * POST /api/catalogue/template/delete
     * Ištrina katalogą templates/{directory}/{folderName}.
     * Body: { "directory": "4 Tvarkos", "folderName": "Senas" }
     * arba { "directory": "4 Tvarkos/Senas" } – trinamas visas directory
     */
    #[Route('/api/catalogue/template/delete', name: 'api_catalogue_template_delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $directory = trim((string) ($data['directory'] ?? ''));
        $folderName = trim((string) ($data['folderName'] ?? ''));

        if ($directory === '') {
            return new JsonResponse(['status' => 'FAIL'], 400);
        }

        $status = $this->deleteCatalogue->delete($directory, $folderName);

        return new JsonResponse(['status' => $status], $status === 'SUCCESS' ? 200 : 500);
    }
}
