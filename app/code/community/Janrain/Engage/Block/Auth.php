<?php

class Janrain_Engage_Block_Auth extends Mage_Core_Block_Template implements Mage_Widget_Block_Interface {

    function rpx_small_buttons() {
            return '<div id="janrainEngageEmbed"></div>';
    }

    protected function _toHtml() {
        $content = '';
        if (Mage::getSingleton('customer/session')->isLoggedIn() == false)
            $content = $this->rpx_small_buttons();
        return $content;
    }

    protected function _prepareLayout() {
        if ($this->getLayout()->getBlock('janrain_engage_scripts') == false) {
            $size = ($this->getSize() == 'inline') ? 'embed' : 'modal';
            $block = $this->getLayout()
                ->createBlock('core/template', 'janrain_engage_scripts')
                ->setData('size', $size)
                ->setTemplate('janrain/engage/auth.phtml');
            $this->getLayout()->getBlock('before_body_end')->insert($block);
        }

        parent::_prepareLayout();
    }

}
