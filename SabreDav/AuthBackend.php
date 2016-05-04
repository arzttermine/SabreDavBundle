<?php
namespace Arzttermine\SabreDavBundle\SabreDav;

use Sabre\DAV\Auth\Backend\AbstractDigest;


class AuthBackend extends AbstractDigest
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var \FOS\UserBundle\Model\UserManagerInterface
     */
    private $user_manager;

    /**
     * Constructor.
     *
     */
    public function __construct($realm, $em, $um)
    {
        $this->em = $em;
        $this->user_manager = $um;
        $this->setRealm($realm);
    }

    /**
     * Returns the digest hash for a user.
     *
     * @param string $realm
     * @param string $username
     * @return string|null
     */
    function getDigestHash($realm, $username) 
    {
        $user = $this->user_manager->findUserByUsername($username);

        return $user->getCalDigest() ?: null;
    }
}

