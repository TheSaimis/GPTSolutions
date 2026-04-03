<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CompanyType;
use App\Repository\CompanyTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/company-types')]
final class CompanyTypeController extends AbstractController
{
    #[Route('', name: 'api_company_types_all', methods: ['GET'])]
    public function all(CompanyTypeRepository $repo): JsonResponse
    {
        $rows = $repo->findBy([], ['typeShort' => 'ASC']);
        $out  = array_map(static function (CompanyType $t): array {
            return [
                'id'          => $t->getId(),
                'typeShort'   => $t->getTypeShort(),
                'typeShortEn' => $t->getTypeShortEn(),
                'typeShortRu' => $t->getTypeShortRu(),
                'type'        => $t->getType(),
                'typeEn'      => $t->getTypeEn(),
                'typeRu'      => $t->getTypeRu(),
            ];
        }, $rows);

        return new JsonResponse($out);
    }
}
