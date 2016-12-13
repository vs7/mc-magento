<?php
/**
 * mc-magento Magento Component
 *
 * @category Ebizmarts
 * @package mc-magento
 * @author Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @date: 5/27/16 1:50 PM
 * @file: EcommerceController.php
 */
class Ebizmarts_Mailchimp_Adminhtml_EcommerceController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Reset local Errors for scope in param.
     */
    public function resetLocalErrorsAction()
    {
        $param = Mage::app()->getRequest()->getParam('scope');
        $scopeArray = explode('-', $param);
        $result = 1;
        try {
            Mage::helper('mailchimp')->resetErrors($scopeArray[0], $scopeArray[1]);
        } catch(Exception $e)
        {
            $result = 0;
        }
        Mage::app()->getResponse()->setBody($result);
    }

    /**
     * Reset Ecommerce Data for scope in param.
     */
    public function resetEcommerceDataAction()
    {
        $param = Mage::app()->getRequest()->getParam('scope');
        $scopeArray = explode('-', $param);
        $result = 1;
        try {
            Mage::helper('mailchimp')->resetMCEcommerceData(true, $scopeArray[0], $scopeArray[1]);
        }
        catch(Mailchimp_Error $e) {
            Mage::helper('mailchimp')->logError($e->getFriendlyMessage());
            $result = 0;
        }
        catch(Exception $e) {
            Mage::helper('mailchimp')->logError($e->getMessage());
        }
        Mage::app()->getResponse()->setBody($result);
    }

    /**
     * Create Merge Fields in the list selected for the scope in param.
     */
    public function createMergeFieldsAction()
    {
        $param = Mage::app()->getRequest()->getParam('scope');
        $scopeArray = explode('-', $param);
        $result = 1;
        try {
            Mage::helper('mailchimp')->createMergeFields($scopeArray[0], $scopeArray[1]);
        }
        catch(Mailchimp_Error $e) {
            Mage::helper('mailchimp')->logError($e->getFriendlyMessage());
            $result = 0;
        }
        catch(Exception $e) {
            Mage::helper('mailchimp')->logError($e->getMessage());
        }
        Mage::app()->getResponse()->setBody($result);
    }

    /**
     * Grant access to any back end user with permission to the extension.
     * 
     * @return mixed
     */
    protected function _isAllowed()
    {
        switch ($this->getRequest()->getActionName()) {
            case 'resetLocalErrors':
            case 'resetEcommerceData':
            case 'createMergeFields':
                $acl = 'system/config/mailchimp';
                break;
        }
        return Mage::getSingleton('admin/session')->isAllowed($acl);
    }
}