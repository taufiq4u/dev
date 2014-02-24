<?php

require_once ('Mage/Customer/controllers/AccountController.php');

class Janrain_Engage_RpxController extends Mage_Customer_AccountController {

    /**
     * Action predispatch
     *
     * Check customer authentication for some actions
     *
     * This is a clone of the one in Mage_Customer_AccountController
     * with two added action names to the preg_match regex to prevent
     * redirects back to customer/account/login when using Engage
     * authentication links. Rather than calling parent::preDispatch()
     * we explicitly call Mage_Core_Controller_Front_Action to prevent the
     * original preg_match test from breaking our auth process.
     *
     */
    public function preDispatch() {
        // a brute-force protection here would be nice

        Mage_Core_Controller_Front_Action::preDispatch();

        if (!$this->getRequest()->isDispatched()) {
            return;
        }

        $action = $this->getRequest()->getActionName();
        if (!preg_match('/^(xdcomm|token_url_add|token_url|duplicate|create|login|logoutSuccess|forgotpassword|forgotpasswordpost|confirm|confirmation)/i', $action)) {
            if (!$this->_getSession()->authenticate($this)) {
                $this->setFlag('', 'no-dispatch', true);
            }
        } else {
            $this->_getSession()->setNoReferer(true);
        }
    }

    public function indexAction() {
        $this->_redirect('customer/account/index');
    }

    /**
     * RPX Callback
     */
    public function token_urlAction() {
        $session = $this->_getSession();
        // Redirect if user is already authenticated
        if ($session->isLoggedIn()) {
            $this->_redirect('customer/account');
            return;
        }

        $token = $this->getRequest()->getPost('token');
        $auth_info = Mage::helper('engage/rpxcall')->rpxAuthInfoCall($token);
        if (isset($auth_info->stat) && $auth_info->stat == 'ok') {
            $customer = Mage::helper('engage/identifiers')->get_customer($auth_info->profile->identifier);

            if ($customer === false) {
                if (isset($auth_info->profile) && isset($auth_info->profile->verifiedEmail)) {
                    $email = $auth_info->profile->verifiedEmail;
                } elseif (isset($auth_info->profile) && isset($auth_info->profile->email)) {
                    $email = $auth_info->profile->email;
                } else {
                    $email = '';
                }
                $firstName = Mage::helper('engage/rpxcall')->getFirstName($auth_info);
                $lastName = Mage::helper('engage/rpxcall')->getLastName($auth_info);
                $profile = Mage::helper('engage')->buildProfile($auth_info);
                Mage::getSingleton('engage/session')->setIdentifier($profile);

                // TODO: Create an account merging process
                //$existing = Mage::getModel('customer/customer')
                //    ->getCollection()
                //    ->addFieldToFilter('email', $email)
                //    ->getFirstItem();
                $isSeamless = ('1' == Mage::getStoreConfig('engage/options/seamless'));
                if ($isSeamless && $email && $firstName && $lastName) {
                    $customer = Mage::getModel('customer/customer')->setId(null);
                    $customer->getGroupId();
                    $customer->setFirstname($firstName);
                    $customer->setLastname($lastName);
                    $customer->setEmail($email);

                    $password = md5('Janrain_Engage_' . Mage::helper('engage')->rand_str(12));
                    $_POST['password'] = $password;
                    $_POST['confirmation'] = $password;
                    Mage::register('current_customer', $customer);

                    $this->_forward('createPost');
                } else {
                    $this->loadLayout();
                    $block = Mage::getSingleton('core/layout')->getBlock('customer_form_register');
                    if ($block !== false) {
                        $form_data = $block->getFormData();

                        $form_data->setEmail($email);
                        $form_data->setFirstname($firstName);
                        $form_data->setLastname($lastName);
                    }

                    $this->renderLayout();
                }
                return;
            } else {
                Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
                $this->_loginPostRedirect();
            }
        } else {
            $session->addWarning('Could not retrieve account info. Please try again.');
            $this->_redirect('customer/account/login');
        }
    }

    /**
     * RPX Callback for Additional Identifiers
     */
    public function token_url_addAction() {
        $session = $this->_getSession();
        // Redirect if user isn't already authenticated
        if (!$session->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            return;
        }

        $token = $this->getRequest()->getPost('token');
        $auth_info = Mage::helper('engage/rpxcall')->rpxAuthInfoCall($token);

        $customer = Mage::helper('engage/identifiers')->get_customer($auth_info->profile->identifier);

        if ($customer === false) {
            $customer_id = $session->getCustomerId();
            $profile = Mage::helper('engage')->buildProfile($auth_info);

            Mage::helper('engage/identifiers')->save_identifier($customer_id, $profile);

            $session->addSuccess('New provider successfully added.');
        } else {
            $session->addWarning('Could not add Provider. This account is already associated with a user.');
        }

        $this->_redirect('customer/account');
    }

    public function createPostAction() {
        $session = $this->_getSession();
        parent::createPostAction();

        $messages = $session->getMessages();
        $isError = false;

        foreach ($messages->getItems() as $message) {
            if ($message->getType() == 'error') {
                $isError = true;
            }
        }

        if ($isError) {
            $email = $this->getRequest()->getPost('email');
            $firstname = $this->getRequest()->getPost('firstname');
            $lastname = $this->getRequest()->getPost('lastname');
            Mage::getSingleton('engage/session')->setEmail($email)->setFirstname($firstname)->setLastname($lastname);
            $this->_redirect('engage/rpx/duplicate');
        }

        return;
    }

    public function duplicateAction() {
        $session = $this->_getSession();

        // Redirect if user is already authenticated
        if ($session->isLoggedIn()) {
            $this->_redirect('customer/account');
            return;
        }

        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $block = Mage::getSingleton('core/layout')->getBlock('customer_form_register');
        $block->setUsername(Mage::getSingleton('engage/session')->getEmail());
        $block->getFormData()->setEmail(Mage::getSingleton('engage/session')->getEmail());
        $block->getFormData()->setFirstname(Mage::getSingleton('engage/session')->getFirstname());
        $block->getFormData()->setLastname(Mage::getSingleton('engage/session')->getLastname());
        $this->renderLayout();
    }

    public function loginPostAction() {
        parent::loginPostAction();
    }

    protected function _loginPostRedirect() {
        $session = $this->_getSession();
        if ($session->isLoggedIn()) {
            if ($profile = Mage::getSingleton('engage/session')->getIdentifier()) {
                $customer = $session->getCustomer();
                Mage::helper('engage/identifiers')->save_identifier($customer->getId(), $profile);
                Mage::getSingleton('engage/session')->setIdentifier(false);
            }
        }

        parent::_loginPostRedirect();
    }

    public function removeIdAction() {
        $session = $this->_getSession();
        $id = $this->getRequest()->getParam('identifier');

        Mage::helper('engage/identifiers')->delete_identifier($id);
        $session->addSuccess('Provider removed');
        $this->_redirect('customer/account');
    }

}
