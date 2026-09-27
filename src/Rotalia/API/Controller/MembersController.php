<?php

namespace Rotalia\API\Controller;

use App\Entity\Member;
use App\Entity\User;
use App\Repository\MemberRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class MembersController extends DefaultController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[Route('/members', methods: ['GET'])]
    public function list(
        MemberRepository $memberQuery,
        #[MapQueryParameter] ?string $name = null,
        #[MapQueryParameter] ?int $conventId = null,
        #[MapQueryParameter] ?string $isActive = null,
        #[MapQueryParameter] int $limit = 10,
        #[MapQueryParameter] int $offset = 0,
    ): JsonResponse
    {
        $query = $memberQuery->createQueryBuilder('m')
            ->orderBy('m.firstName')
            ->addOrderBy('m.lastName')
        ;

        if ($limit) {
            $query->setFirstResult($offset)->setMaxResults($limit);
        }

        if (!empty($conventId)) {
            $query->andWhere('IDENTITY(m.convent) = :conventId')->setParameter('conventId', $conventId);
        }

        if (!empty($name)) {
            $query
                ->andWhere("CONCAT(m.firstName, ' ', m.lastName) LIKE :name")
                ->setParameter('name', $name . '%')
            ;
        }

        if ($isActive !== null) {
            if (filter_var($isActive, FILTER_VALIDATE_BOOLEAN)) {
                $query->andWhere('m.lahk_pohjused_id = 0');
            } else {
                $query->andWhere('m.lahk_pohjused_id > 0');
            }
        }

        /** @var Member[] $members */
        $members = $query->getQuery()->getResult();

        /** @var User $user */
        $user = $this->getUser();
        $memberConventId = $user->getMember()->getConventId();
        $isSuperAdmin = $this->isGranted(User::ROLE_SUPER_ADMIN);
        $isAdmin = $this->isGranted(User::ROLE_ADMIN);

        $result = [];
        foreach ($members as $member) {
            // Show credit balance only for members of the admin's own convent, or for all if super admin
            $includeCredit = $isSuperAdmin || ($isAdmin && $member->getConventId() === $memberConventId);
            $result[] = $member->getAjaxData($includeCredit);
        }

        return $this->json(['members' => $result]);
    }
}
