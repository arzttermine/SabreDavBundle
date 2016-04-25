<?php
namespace Arzttermine\SabreDavBundle\SabreDav;

use Sabre\HTTP\Response as BaseResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class HttpResponse.
 */
class HttpResponse extends BaseResponse
{
    /**
     * @var StreamedResponse
     */
    private $response;

    /**
     * Constructor.
     *
     * @param StreamedResponse $response
     */
    public function __construct(StreamedResponse $response)
    {
        parent::__construct($response->getStatusCode(), $response->headers->all());
        $this->response = $response;
    }
}

