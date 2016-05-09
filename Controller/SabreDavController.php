<?php
namespace Arzttermine\SabreDavBundle\Controller;

use Monolog\Logger;
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
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param Server          $dav
     * @param RouterInterface $router
     */
    public function __construct(Server $dav, RouterInterface $router, Logger $logger)
    {
        $router->getContext()->setBaseUrl($router->getContext()->getBaseUrl());
        $this->dav = $dav;
	    $this->dav::$exposeVersion = false;
        $this->dav->setBaseUri($router->generate('arzttermine_sabre_dav', array()));
        $this->logger = $logger;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return StreamedResponse
     */
    public function execAction(Request $request)
    {
	if($request->headers->has('Content-Length') && $request->headers->get('Content-Length') === '') {
            $request->headers->set('Content-Length', strlen($request->getContent()));
        }

        $dav = $this->dav;
        $callback = function () use ($dav) {
            $dav->exec();
        };
        $response = new StreamedResponse($callback);
        $dav->httpRequest = new HttpRequest($request);
        $dav->httpResponse = new Response($response->getStatusCode(), $response->headers->all());

        $this->logger->error('REQUEST: '.$dav->httpRequest);
        $this->logger->error('RESPONSE: '.$dav->httpResponse);

        return $response;
    }
}

