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
     * @return bool|mixed
     * @throws Exception
     */
    public function getMailChimpStore()
    {
        $api = Mage::helper('mailchimp')->getApi();

        if ($api) {
            $storeExists = null;
            $storeId = Mage::helper('mailchimp')->getMCStoreId();

            if ($storeId == null || $storeId == "") {
                return null;
            }

            try {
                $store = $api->ecommerce->stores->get($storeId);
                if (is_array($store) && isset($store['id'])) {
                    $storeExists = $store;
                }
            }
            catch (Mailchimp_Error $e) {
                Mage::helper('mailchimp')->logError($e->getFriendlyMessage());
            }
            catch (Exception $e) {
                Mage::helper('mailchimp')->logError($e->getMessage());
            }

            return $storeExists;
        } else {
            throw new Exception('You must provide a MailChimp API key');
        }
    }

    /**
     * @param $storeId
     * @param null $listId
     * @throws Exception
     */
    public function createMailChimpStore($storeId, $listId=null)
    {
        $api = Mage::helper('mailchimp')->getApi();
        if ($api) {
            if (!$listId) {
                $listId = Mage::helper('mailchimp')->getConfigValue(Ebizmarts_MailChimp_Model_Config::GENERAL_LIST);
            }

            if ($listId != null && $listId != "") {
                $storeName = Mage::helper('mailchimp')->getMCStoreName();
                $storeEmail = Mage::helper('mailchimp')->getConfigValue('trans_email/ident_general/email');
                if (strpos($storeEmail, 'example.com') !== false) {
                    $storeEmail = null;
                    throw new Exception('Please, change the general email in Store Email Addresses/General Contact');
                }

                $currencyCode = Mage::helper('mailchimp')->getConfigValue(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_DEFAULT);
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
    public function deleteStore($storeId)
    {
        $api = Mage::helper('mailchimp')->getApi();
        $api->ecommerce->stores->delete($storeId);
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $resource = Mage::getResourceModel('mailchimp/synchbatches');
        $connection->update($resource->getMainTable(), array('status'=>'canceled'), "status = 'pending'");
    }
    public function modifyName($name)
    {
        $api = Mage::helper('mailchimp')->getApi();
        $storeId = Mage::helper('mailchimp')->getMCStoreId();
        $api->ecommerce->stores->edit($storeId, $name);
    }
}