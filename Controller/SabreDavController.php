<?php
namespace Arzttermine\SabreDavBundle\Controller;

use Sabre\DAV\Server;
use Sabre\HTTP\Response;
use Arzttermine\SabreDavBundle\SabreDav\HttpRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class SabreDavController.
 */
class SabreDavController
{
    /**
     * @var Server
     */
    private $dav;

    /**
     * Constructor.
     *
     * @param Server          $dav
     * @param RouterInterface $router
     */
    public function __construct(Server $dav, RouterInterface $router)
    {
        $router->getContext()->setBaseUrl($router->getContext()->getBaseUrl());
        $this->dav = $dav;
        $this->dav->setBaseUri($router->generate('arzttermine_sabre_dav', array()));
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return StreamedResponse
     */
    public function execAction(Request $request)
    {
        $dav = $this->dav;
        $callback = function () use ($dav) {
            $dav->exec();
        };
        $response = new StreamedResponse($callback);
        $dav->httpRequest = new HttpRequest($request);
        $dav->httpResponse = new Response($response->getStatusCode(), $response->headers->all());

        return $response;
    }
}

