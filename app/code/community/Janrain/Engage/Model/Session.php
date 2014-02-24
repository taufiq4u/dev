<?php

class Janrain_Engage_Model_Session extends Mage_Core_Model_Session_Abstract {

    public function __construct() {
        $namespace = 'engage';
        $namespace .= '_' . (Mage::app()->getStore()->getWebsite()->getCode());

        $this->init($namespace);
        Mage::dispatchEvent('engage_session_init', array('engage_session' => $this));
    }

}
