<?php

/**
 * mailchimp-lib Magento Component
 *
 * @category Ebizmarts
 * @package mailchimp-lib
 * @author Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Ebizmarts_MailChimp_Model_Api_Stores
{

    /**
     * @param $storeId
     * @param null $listId
     * @param $scope
     * @param $scopeId
     * @throws Exception
     */
    public function createMailChimpStore($storeId, $listId=null, $scope, $scopeId)
    {
        $api = Mage::helper('mailchimp')->getApi($scope, $scopeId);
        if ($api) {
            if (!$listId) {
                $listId = Mage::getModel('mailchimp/config')->getDefaultList($scope, $scopeId);
            }

            if ($listId != null && $listId != "") {
                $storeName = Mage::helper('mailchimp')->getMCStoreName($scope, $scopeId);
                $storeEmail = Mage::getModel('mailchimp/config')->getConfigValueForScope('trans_email/ident_general/email', $scope, $scopeId);
                if (strpos($storeEmail, 'example.com') !== false) {
                    $storeEmail = null;
                    throw new Exception('Please, change the general email in Store Email Addresses/General Contact');
                }

                $currencyCode = Mage::getModel('mailchimp/config')->getConfigValueForScope(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_DEFAULT, $scope, $scopeId);
                $isSyncing = true;
                $api->ecommerce->stores->add($storeId, $listId, $storeName, $currencyCode, $isSyncing, 'Magento', null, $storeEmail);
            } else {
                throw new Exception('You don\'t have any lists configured in MailChimp');
            }
        } else {
            throw new Exception('You must provide a MailChimp API key');
        }
    }

    /**
     * @param $storeId
     */
    public function deleteStore($scope, $scopeId, $MCStoreId)
    {
        $api = Mage::helper('mailchimp')->getApi($scope, $scopeId);
        $api->ecommerce->stores->delete($MCStoreId);
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $resource = Mage::getResourceModel('mailchimp/syncbatches');
        $connection->update($resource->getMainTable(), array('status'=>'canceled'), "status = 'pending' AND store_id = '" . $MCStoreId . "'");
    }
}