<?php

namespace Arzttermine\SabreDavBundle\SabreDav;

use Sabre\DAVACL\Plugin;

/**
 * Class extends the SabreDAV ACL Plugin to provide some additional methods and setter for public variables.
 *
 * @author lduer
 */
class ACLPlugin extends Plugin
{
    /**
     * By default nodes that are inaccessible by the user, can still be seen
     * in directory listings (PROPFIND on parent with Depth: 1).
     *
     * @param bool $flag
     */
    public function setHideNodesFromListings($flag = true)
    {
        $this->hideNodesFromListings = (bool) $flag;
    }

    /**
     * By default ACL is only enforced for nodes that have ACL support (the
     * ones that implement IACL). For any other node, access is
     * always granted.
     *
     * To override this behaviour you can turn this setting off. This is useful
     * if you plan to fully support ACL in the entire tree.
     *
     * @param bool $flag
     */
    public function setAccessToNodesWithoutACL($flag = true)
    {
        $this->allowAccessToNodesWithoutACL = (bool) $flag;
    }
}

