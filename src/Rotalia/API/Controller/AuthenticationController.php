<?php

namespace Rotalia\API\Controller;

use App\Entity\User;
use App\Repository\PointOfSaleRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authenticator\JsonLoginAuthenticator;
use Throwable;

class AuthenticationController extends DefaultController
{
    /**
     * @throws Throwable
     */
    #[Route('authentication', name: 'login_check', methods: ['GET'])]
    public function check(
        PointOfSaleRepository $pointOfSaleQuery,
        Request $request,
        #[CurrentUser] ?User $user,
    ): JsonResponse
    {
        $pos = $this->getPos($request, $pointOfSaleQuery);

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

        $response = $this->json([
            'member' => $memberData,
            'pointOfSaleId' => $pos?->getId(),
        ]);

        if ($pos === null && $request->cookies->get('pos_hash')) {
            // Delete a stale/invalid pos_hash cookie
            $response->headers->setCookie(new Cookie('pos_hash', 'deleted', 1));
        } elseif ($pos !== null) {
            // Refresh cookie lifetime
            $response->headers->setCookie(new Cookie('pos_hash', $pos->getHash(), new \DateTime('+1 year')));
        }

        return $response;
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
        $this->getService('security.token_storage')->setToken(null);
        return JSendResponse::createSuccess('Väljalogimine õnnestus');
    }
}
