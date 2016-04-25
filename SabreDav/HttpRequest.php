<?php
namespace Arzttermine\SabreDavBundle\SabreDav;

use Sabre\HTTP\Request as BaseRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class HttpRequest.
 */
class HttpRequest extends BaseRequest
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $currentUsername;

    /**
     * Constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        parent::__construct($request->getMethod(), $request->getRequestUri(), $request->headers->all(), $request->getContent(true));
        $this->request = $request;
    }

    /**
     * set the current username.
     * 
     * @param string $username
     */
    public function setCurrentUsername($username)
    {
        $this->currentUsername = $username;
    }

    /**
     * get the current username.
     * 
     * @return string
     */
    public function getCurrentUsername()
    {
        return $this->currentUsername;
    }
}

