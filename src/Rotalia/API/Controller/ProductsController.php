<?php

namespace Rotalia\API\Controller;

use App\Entity\Product;
use App\Entity\Enum\ProductStatus;
use App\Entity\ProductInfo;
use App\Entity\User;
use App\Form\FormHelper;
use App\Form\ProductType;
use App\Repository\ConventRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Rotalia\APIBundle\Component\HttpFoundation\JSendResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class ProductsController extends DefaultController
{
    /**
     * @param ProductRepository $productQuery
     * @param string|null $name
     * @param string|null $productCode
     * @param int|null $productGroup
     * @param string|null $active
     * @param int $page
     * @param int $limit
     * @param int|null $conventId
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/products', methods: ['GET'])]
    public function list(
        ProductRepository $productQuery,
        #[MapQueryParameter] ?string $name = null,
        #[MapQueryParameter] ?string $productCode = null,
        #[MapQueryParameter] ?int $productGroup = null,
        #[MapQueryParameter] ?string $active = null,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 10,
        #[MapQueryParameter] int $conventId = null,
    ): JsonResponse
    {
        $activeConventId = $this->setActiveConventId($conventId);

        $offset = ($page - 1) * $limit;
        $query = $productQuery->createQueryBuilder('p');
        $productQuery->orderBySeqForConvent($query, $activeConventId);
        $query
            ->setFirstResult($offset)
            ->setMaxResults($limit)
        ;

        if ($active !== null) {
            $query
                ->innerJoin('p.productInfos', 'pi', Join::WITH, 'pi.status = :status AND pi.conventId = :conventId')
                ->setParameter('status', filter_var($active, FILTER_VALIDATE_BOOLEAN) ? ProductStatus::ACTIVE : ProductStatus::DISABLED)
                ->setParameter('conventId', $activeConventId)
            ;
        }

        if ($name !== null) {
            $query
                ->andWhere('p.name LIKE :name')
                ->setParameter('name', '%'.$name.'%')
            ;
        }

        if ($productCode !== null) {
            $query
                ->andWhere('FIND_IN_SET(:productCode, p.productCode) > 0')
                ->setParameter('productCode', $productCode)
            ;
        }

        if ($productGroup !== null) {
            $query
                ->andWhere('p.productGroup = :productGroup')
                ->setParameter('productGroup', $productGroup);
        }

//        $sql = $query->getQuery()->getSQL();
//        return $this->json(['sql' => $query->getQuery()->getSQL()]);


        /** @var Product[] $products */
        $products = $query->getQuery()->getResult();

        $count = $query->select('count(distinct p.id)')->getQuery()->getSingleScalarResult();

        return $this->json([
            'products' => $products,
            'page' => $page,
            'pages' => ceil((int)$count / $limit),
        ]);
    }

    /**
     * @param EntityManagerInterface $em
     * @param ConventRepository $conventRepository
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    #[Route('/products', methods: ['POST'])]
    public function create(
        EntityManagerInterface $em,
        ConventRepository $conventRepository,
        Request $request,
    ): JsonResponse
    {
        $conventId = $request->request->get('conventId');

        $this->setActiveConventId($conventId);

        $product = new Product();
        $response = $this->handleSubmit($product, $request, $em);

        if (!$response->isSuccessful()) {
            return $response;
        }

        $infos = $product->getProductInfos();
        $infoConventIds = array_map(static function (ProductInfo $info) { return $info->getConventId(); }, $infos->toArray());

        $activeConvents = $conventRepository->getActiveConvents();

        // Add entries for other active convents too that are missing
        foreach ($activeConvents as $convent) {
            if (!in_array($convent->getId(), $infoConventIds, true)) {
                $productInfo = new ProductInfo();
                $productInfo
                    ->setProduct($product)
                    ->setConvent($convent)
                    ->setPrice($product->getPrice()) // use the same price
                    ->setSeq($product->getSeq()) // use the same sequence number
                    ->setStatus(ProductStatus::DISABLED->value) // but set disabled
                ;
                $em->persist($productInfo);
            }
        }
        $em->flush();

        return $response;
    }

    /**
     * @param EntityManagerInterface $em
     * @param ProductRepository $productQuery
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws Throwable
     */
    #[Route('/products/{id}', methods: ['PATCH'])]
    public function update(
        EntityManagerInterface $em,
        ProductRepository $productQuery,
        Request $request,
        int $id,
    ): JsonResponse
    {
        $product = $productQuery->findOneBy(['id' => $id]);

        if (!$product) {
            return JSendResponse::createFail('Toodet ei leitud', 404);
        }

        $conventId = $request->request->get('conventId');
        $this->setActiveConventId($conventId);

        return $this->handleSubmit($product, $request, $em);
    }

    /**
     * @param Product $product
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return JSendResponse
     * @throws Throwable
     */
    private function handleSubmit(Product $product, Request $request, EntityManagerInterface $em): JSendResponse
    {
        $this->requireAdmin();

        return FormHelper::submitForm($this->container, $request, $em, ProductType::class, $product);
    }

    private function setActiveConventId(?int $conventId): ?int
    {
        $activeConventId = $conventId;
        if ($conventId === null) {
            /** @var User $user */
            $user = $this->getUser();
            $activeConventId = $user->getMember()->getConventId();
        }
        Product::$activeConventId = $activeConventId;

        return $activeConventId;
    }
}
