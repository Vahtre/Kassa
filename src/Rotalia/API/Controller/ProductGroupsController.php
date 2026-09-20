<?php

namespace Rotalia\API\Controller;

use App\Entity\ProductGroup;
use App\Repository\ProductGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Component\HttpFoundation\JSendResponse;
use App\Form\FormHelper;
use App\Form\ProductGroupType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class ProductGroupsController extends DefaultController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/productGroups', methods: ['GET'])]
    public function list(
        ProductGroupRepository $groupQuery,
    ): JsonResponse
    {
        $query = $groupQuery->createQueryBuilder('g');
        $query
            ->orderBy('g.seq')
        ;

        /** @var ProductGroup[] $groups */
        $groups = $query->getQuery()->getResult();

        return $this->json([
            'productGroups' => $groups,
        ]);
    }

    /**
     * @throws Throwable
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    #[Route('/productGroups', methods: ['POST'])]
    public function create(
        EntityManagerInterface $em,
        Request $request,
    ): JsonResponse
    {
        return $this->handleSubmit(new ProductGroup(), $request, $em);
    }

    /**
     * @throws Throwable
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    #[Route('/productGroups/{id}', methods: ['PATCH'])]
    public function update(
        EntityManagerInterface $em,
        ProductGroupRepository $groupQuery,
        Request $request,
        int $id,
    ): JsonResponse
    {
        $group = $groupQuery->findOneBy(['id' => $id]);

        if (!$group) {
            return JSendResponse::createFail('Tootegruppi ei leitud', 404);
        }

        return $this->handleSubmit($group, $request, $em);
    }

    /**
     * @throws Throwable
     */
    private function handleSubmit(ProductGroup $productGroup, Request $request, EntityManagerInterface $em): JSendResponse
    {
        $this->requireAdmin();

        $form = $this->createForm(ProductGroupType::class, $productGroup, [
            'csrf_protection' => false, // Disable for REST api
            'method' => $request->getMethod(),
        ]);

        return FormHelper::handleFormSubmit($form, $request, $em);
    }
}
