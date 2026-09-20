<?php

namespace Rotalia\API\Controller;

use App\Entity\User;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authenticator\JsonLoginAuthenticator;

class AuthenticationController extends DefaultController
{
    /**
     * @throws \Throwable
     */
    #[Route('authentication', name: 'login_check', methods: ['GET'])]
    public function check(
        #[CurrentUser] ?User $user,
    ): JsonResponse
    {
        $memberData = null;
        if ($user !== null) {
            $member = $user->getMember();
            $memberData = [
                'id' => $user->getLiikmedId(),
                'name' => $member->getFullName(),
                'conventId' => $member?->getConventId(),
                'creditBalance' => $member?->getTotalCredit(),
                'roles' => $user->getRoles(),
            ];
        }

        return $this->json([
            'member' => $memberData,
            'pointOfSaleId' => null, // $pos ? $pos->getId() : null, // TODO: PointOfSale
        ]);
    }

    /**
     * json_login route is configured under security.firewall.main.json_login.check_path
     * JsonLoginAuthenticator takes JSON payload with username/password attributes,
     * verifies the request and credentials, logs the user in with RememberMe badge.
     * When authorization fails, it will trigger AuthenticationfailureHandler::onAuth
     * @see JsonLoginAuthenticator
     * @throws ContainerExceptionInterface
     * @throws \Throwable
     * @throws NotFoundExceptionInterface
     */
    #[Route('authentication', name: 'json_login', methods: ['POST'])]
    public function jsonLogin(): JsonResponse
    {
        return JSendResponse::createSuccess('Autoriseerimine õnnestus');
    }

    /**
     * @throws \Throwable
     */
    #[Route('authentication', name: 'json_logout', methods: ['DELETE'])]
    public function logout(): JsonResponse
    {
        $this->get('security.token_storage')->setToken(null);
        return JSendResponse::createSuccess('Väljalogimine õnnestus');
    }
}
