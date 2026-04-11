<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/categories')]
final class CategoryController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('', name: 'api_categories_all', methods: ['GET'])]
    public function all(CategoryRepository $repo): JsonResponse
    {
        $items = $repo->createQueryBuilder('c')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (Category $item): array => [
                'id' => $item->getId(),
                'name' => $item->getName(),
            ],
            $items
        );

        return new JsonResponse($data);
    }

    #[Route('', name: 'api_categories_create', methods: ['POST'])]
    public function create(Request $request, CategoryRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Neteisingas JSON'], 400);
        }

        $name = (string) ($data['name'] ?? $data['category'] ?? $data['catagory'] ?? '');
        $name = trim($name);
        if ($name === '') {
            return new JsonResponse(['status' => 'FAIL', 'error' => 'Būtinas kategorijos pavadinimas'], 400);
        }

        $existing = $repo->createQueryBuilder('c')
            ->where('LOWER(TRIM(c.name)) = LOWER(:name)')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof Category) {
            return new JsonResponse([
                'status' => 'SUCCESS',
                'data' => ['id' => $existing->getId(), 'name' => $existing->getName()],
            ], 200);
        }

        $category = (new Category())->setName($name);
        $this->em->persist($category);
        $this->em->flush();

        return new JsonResponse([
            'status' => 'SUCCESS',
            'data' => ['id' => $category->getId(), 'name' => $category->getName()],
        ], 201);
    }
}

