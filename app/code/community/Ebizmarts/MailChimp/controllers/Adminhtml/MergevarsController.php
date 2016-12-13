<?php
/**
 * mc-magento Magento Component
 *
 * @category Ebizmarts
 * @package mc-magento
 * @author Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @file: MergevarsController.php
 */
class Ebizmarts_Mailchimp_Adminhtml_MergevarsController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Load layout.
     */
    public function addmergevarAction(){

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Add custom Merge field.
     */
    public function saveaddAction()
    {
        $postData = $this->getRequest()->getPost('mergevar', array());
        $label = $postData['label'];
        $value = $postData['value'];
        $fieldType = $postData['fieldtype'];
        $customFieldTypes = unserialize(Mage::getModel('mailchimp/config')->getCustomMapFields());

        if(!$customFieldTypes){
            $customFieldTypes = array();
        }
        $customFieldTypes[] = array('label' => $label, 'value' => $value, 'field_type' => $fieldType);
        Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_CUSTOM_MAP_FIELDS, serialize($customFieldTypes), 'default', 0);
        Mage::getConfig()->cleanCache();
        Mage::getSingleton('core/session')->setMailChimpValue($value);
        Mage::getSingleton('core/session')->setMailChimpLabel($label);
        Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The custom value was added successfully.'));
        $this->_redirect("*/*/addmergevar");

    }

    /**
     * Grant access to any back end user with permission to the extension.
     * 
     * @return mixed
     */
    protected function _isAllowed()
    {
        switch ($this->getRequest()->getActionName()) {
            case 'addmergevar':
            case 'saveadd':
                $acl = 'system/config/mailchimp';
                break;
        }
        return Mage::getSingleton('admin/session')->isAllowed($acl);
    }
}