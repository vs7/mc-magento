<?php
/**
 * MailChimp For Magento
 *
 * @category Ebizmarts_MailChimp
 * @author Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @date: 4/29/16 3:55 PM
 * @file: Data.php
 */
class Ebizmarts_MailChimp_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * Get storeId and/or websiteId if scope selected on back end
     *
     * @param null $storeId
     * @param null $websiteId
     * @return array
     */
    public function getConfigScopeId($storeId = null, $websiteId = null)
    {
        $scopeArray = array();
        if ($code = Mage::getSingleton('adminhtml/config_data')->getStore()) {
            // store level
            $storeId = Mage::getModel('core/store')->load($code)->getId();
        } elseif ($code = Mage::getSingleton('adminhtml/config_data')->getWebsite()) {
            // website level
            $websiteId = Mage::getModel('core/website')->load($code)->getId();
            $storeId = Mage::app()->getWebsite($websiteId)->getDefaultStore()->getId();
        }
        $scopeArray['websiteId'] = $websiteId;
        $scopeArray['storeId'] = $storeId;
        return $scopeArray;
    }

    /**
     * Create string for current scope with format scope-scopeId.
     *
     * @return string
     */
    public function getScopeString() {
        $scopeArray = $this->getConfigScopeId();
        if (isset($scopeArray['websiteId'])) {
            $scopeString = 'websites-'.$scopeArray['websiteId'];
        } elseif (isset($scopeArray['storeId'])) {
            $scopeString = 'stores-'.$scopeArray['storeId'];
        } else {
            $scopeString = 'default-0';
        }
        return $scopeString;
    }

    /**
     * Get all stores for certain scope.
     *
     * @param $scope
     * @param $scopeId
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getStoresForScope($scope, $scopeId)
    {
        $stores = array();
        switch ($scope) {
            case 'default':
                $stores = Mage::app()->getStores();
                break;
            case 'websites':
                $stores = Mage::app()->getWebsite($scopeId)->getStores();
                break;
            case 'stores':
                $stores = array(Mage::app()->getStore($scopeId));
                break;
        }
        return $stores;
    }

    /**
     * Filter collection by all the stores in certain scope.
     *
     * @param $collection
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function addStoresToFilter($collection, $scope, $scopeId)
    {
        $storesForScope = $this->getStoresForScope($scope, $scopeId);
        $filterArray = array();
        if ($scopeId === 0) {
            $filterArray[] = array('eq' => 0);
        }
        foreach ($storesForScope as $store) {
            $filterArray[] = array('eq' => $store->getId());
        }
        return $collection->addFieldToFilter('store_id', $filterArray);
    }

    /**
     * Get MC store name
     *
     * @param $scope
     * @param $scopeId
     * @return string
     */
    public function getMCStoreName($scope, $scopeId)
    {
        $date = date('Y-m-d-His');
        return $scope . '_' . $scopeId . '_' . $date . '_' . parse_url(Mage::getBaseUrl(), PHP_URL_HOST);
    }

    /**
     * delete MC ecommerce store
     * reset mailchimp store id in the config
     * reset all deltas
     * 
     * @param bool $deleteDataInMailchimp
     * @param $scope
     * @param $scopeId
     */
    public function resetMCEcommerceData($deleteDataInMailchimp = false, $scope, $scopeId)
    {
        $ecommerceEnabled = Mage::getModel('mailchimp/config')->getEcommerceEnabled($scope, $scopeId);
        $apikey = Mage::getModel('mailchimp/config')->getApiKey($scope, $scopeId);
        $listId = Mage::getModel('mailchimp/config')->getDefaultList($scope, $scopeId);
        $MCStoreId = Mage::getModel('mailchimp/config')->getMCStoreIdForScope($scope, $scopeId);

        //delete store id and data from mailchimp
        if ($deleteDataInMailchimp && $MCStoreId && $MCStoreId != "") {
            try {
                Mage::getModel('mailchimp/api_stores')->deleteStore($scope, $scopeId, $MCStoreId);
            } catch(Mailchimp_Error $e) {
                Mage::helper('mailchimp')->logError($e->getFriendlyMessage());
            }
            //clear store config values
            Mage::getConfig()->deleteConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_MCSTOREID, $scope, $scopeId);
        }
        if ($ecommerceEnabled && $apikey && $listId) {
            $this->createStore($listId, $scope, $scopeId);
        }
        //reset mailchimp minimum date to sync flag
        Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_MCMINSYNCDATEFLAG, Varien_Date::now(), $scope, $scopeId);
        Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_MCSTORE_RESETED, 1, $scope, $scopeId);
        Mage::getConfig()->cleanCache();
        $this->resetErrors($scope, $scopeId);
    }

    /**
     * Check if API key is set and the mailchimp store id was configured
     * 
     * @param $scope
     * @param $scopeId
     * @return bool
     */
    public function isEcomSyncDataEnabled($scope, $scopeId)
    {
        $apiKey = Mage::getModel('mailchimp/config')->getApiKey($scope, $scopeId);
        $moduleEnabled = Mage::getModel('mailchimp/config')->getMailChimpEnabled($scope, $scopeId);
        $ecommerceEnabled = Mage::getModel('mailchimp/config')->getEcommerceEnabled($scope, $scopeId);
        $ret = !is_null(Mage::getModel('mailchimp/config')->getMCStoreId($scope, $scopeId)) && Mage::getModel('mailchimp/config')->getMCStoreId($scope, $scopeId) != null
            && !is_null($apiKey) && $apiKey != "" && $moduleEnabled && $ecommerceEnabled;
        return $ret;
    }

    /**
     * Save error response from MailChimp's API in "MailChimp_Error.log" file.
     * 
     * @param $message
     */
    public function logError($message)
    {
        if (Mage::getModel('mailchimp/config')->getConfigValue(Ebizmarts_MailChimp_Model_Config::GENERAL_LOG)) {
            Mage::log($message, null, 'MailChimp_Errors.log', true);
        }
    }

    /**
     * Save request made to MailChimp's API in "$batchId.Request.log" file.
     * 
     * @param $message
     * @param null $batchId
     */
    public function logRequest($message, $batchId=null)
    {
        if (Mage::getModel('mailchimp/config')->getConfigValue(Ebizmarts_MailChimp_Model_Config::GENERAL_LOG)) {
            if (!$batchId) {
                Mage::log($message, null, 'MailChimp_Requests.log', true);
            } else {
                $logDir  = Mage::getBaseDir('var') . DS . 'log';
                $fileName = $logDir.DS.$batchId.'.Request.log';
                file_put_contents($fileName,$message);
            }
        }
    }

    /**
     * @return string
     */
    public function getWebhooksKey($scope, $scopeId)
    {
        $crypt = md5((string)Mage::getConfig()->getNode('global/crypt/key') . $scope . '_' . $scopeId);
        $key = substr($crypt, 0, (strlen($crypt) / 2));

        return $key;
    }

    /**
     * Reset error messages from Products, Subscribers, Customers, Orders, Quotes and set them to be sent again.
     * 
     * @param $scope
     * @param $scopeId
     */
    public function resetErrors($scope, $scopeId)
    {
        // reset products with errors
        $collection = Mage::getModel('mailchimp/productsyncdata')->getCollection()
            ->addFieldToFilter('mailchimp_sync_error', array('notnull' => true))
            ->addFieldToFilter('scope', array('eq' => $scope . '_' . $scopeId));
        foreach ($collection as $productSyncData) {
            $errorCollection = Mage::getModel('mailchimp/mailchimperrors')->getCollection()
                ->addFieldToFilter('regtype', array('eq' => Ebizmarts_MailChimp_Model_Config::IS_PRODUCT))
                ->addFieldToFilter('original_id', array('eq' => $productSyncData->getItemId()))
                ->addFieldToFilter('scope', array(
                    array('neq' => ''),
                    array('null' => true),
                    array('eq' => $scope . '_' . $scopeId)
                ));
            foreach ($errorCollection as $error) {
                $error->delete();
            }
            $productSyncData->setData("mailchimp_sync_delta", '0000-00-00 00:00:00');
            $productSyncData->setData("mailchimp_sync_error", '');
            $productSyncData->save();
        }

        // reset subscribers with errors
        $collection = Mage::getModel('newsletter/subscriber')->getCollection()
            ->addFieldToFilter('mailchimp_sync_error', array('neq' => ''));
        foreach ($collection as $subscriber) {
            $errorCollection = Mage::getModel('mailchimp/mailchimperrors')->getCollection()
                ->addFieldToFilter('regtype', array('eq' => Ebizmarts_MailChimp_Model_Config::IS_SUBSCRIBER))
                ->addFieldToFilter('original_id', array('eq' => $subscriber->getId()))
                ->addFieldToFilter('scope', array(
                    array('neq' => ''),
                    array('null' => true),
                    array('eq' => $scope . '_' . $scopeId)
                ));
            foreach ($errorCollection as $error) {
                $error->delete();
            }
            $subscriber->setData("mailchimp_sync_delta", '0000-00-00 00:00:00');
            $subscriber->setData("mailchimp_sync_error", '');
            $subscriber->save();
        }

        // reset customers with errors
        $collection = Mage::getModel('mailchimp/customersyncdata')->getCollection()
            ->addFieldToFilter('mailchimp_sync_error', array('notnull' => true))
            ->addFieldToFilter('scope', array('eq' => $scope . '_' . $scopeId));
        foreach ($collection as $customerSyncData) {
            $errorCollection = Mage::getModel('mailchimp/mailchimperrors')->getCollection()
                ->addFieldToFilter('regtype', array('eq' => Ebizmarts_MailChimp_Model_Config::IS_CUSTOMER))
                ->addFieldToFilter('original_id', array('eq' => $customerSyncData->getItemId()))
                ->addFieldToFilter('scope', array(
                    array('neq' => ''),
                    array('null' => true),
                    array('eq' => $scope . '_' . $scopeId)
                ));
            foreach ($errorCollection as $error) {
                $error->delete();
            }
            $customerSyncData->setData("mailchimp_sync_delta", '0000-00-00 00:00:00');
            $customerSyncData->setData("mailchimp_sync_error", '');
            $customerSyncData->save();
        }

        // reset orders with errors
        $collection = Mage::getModel('mailchimp/ordersyncdata')->getCollection()
            ->addFieldToFilter('mailchimp_sync_error', array('notnull' => true))
            ->addFieldToFilter('scope', array('eq' => $scope . '_' . $scopeId));
        foreach ($collection as $orderSyncData) {
            $errorCollection = Mage::getModel('mailchimp/mailchimperrors')->getCollection()
                ->addFieldToFilter('regtype', array('eq' => Ebizmarts_MailChimp_Model_Config::IS_ORDER))
                ->addFieldToFilter('original_id', array('eq' => $orderSyncData->getItemId()))
                ->addFieldToFilter('scope', array(
                    array('neq' => ''),
                    array('null' => true),
                    array('eq' => $scope . '_' . $scopeId)
                ));
            foreach ($errorCollection as $error) {
                $error->delete();
            }
            $orderSyncData->setData("mailchimp_sync_delta", '0000-00-00 00:00:00');
            $orderSyncData->setData("mailchimp_sync_error", '');
            $orderSyncData->save();
        }

        // reset quotes with errors
        $collection = Mage::getModel('mailchimp/quotesyncdata')->getCollection()
            ->addFieldToFilter('mailchimp_sync_error', array('notnull' => true))
            ->addFieldToFilter('scope', array('eq' => $scope . '_' . $scopeId));
        foreach ($collection as $quoteSyncData) {
            $errorCollection = Mage::getModel('mailchimp/mailchimperrors')->getCollection()
                ->addFieldToFilter('regtype', array('eq' => Ebizmarts_MailChimp_Model_Config::IS_QUOTE))
                ->addFieldToFilter('original_id', array('eq' => $quoteSyncData->getItemId()))
                ->addFieldToFilter('scope', array(
                    array('neq' => ''),
                    array('null' => true),
                    array('eq' => $scope . '_' . $scopeId)
                ));
            foreach ($errorCollection as $error) {
                $error->delete();
            }
            $quoteSyncData->setData("mailchimp_sync_delta", '0000-00-00 00:00:00');
            $quoteSyncData->setData("mailchimp_sync_error", '');
            $quoteSyncData->save();
        }
    }

    /**
     * Remove order associated campaign id.
     */
    public function resetCampaign()
    {
        $orderCollection = Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter(
                'mailchimp_campaign_id', array(
                array('neq'=>0))
            )
            ->addFieldToFilter(
                'mailchimp_campaign_id', array(
                array('notnull'=>true)
                )
            );
        foreach ($orderCollection as $order) {
            $order->setMailchimpCampaignId(0);
            $order->save();
        }
    }

    /**
     * Create MailChimp store with all required flags for certain scope.
     * 
     * @param $listId
     * @param string $scope
     * @param int $scopeId
     */
    public function createStore($listId, $scope = 'default', $scopeId = 0)
    {
        if ($listId) {
            //generate store id
            $MCStoreId = md5($this->getMCStoreName($scope, $scopeId));
            //create store in mailchimp
            try {
                Mage::getModel('mailchimp/api_stores')->createMailChimpStore($MCStoreId, $listId, $scope, $scopeId);
                //save in config
                Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_MCSTOREID, $MCStoreId, $scope, $scopeId);
                Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_MCISSYNCING, 1, $scope, $scopeId);
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
    }

    /**
     * Delete MailChimp store and flags for certain scope.
     * 
     * @param string $scope
     * @param int $scopeId
     */
    public function deleteStore($scope = 'default', $scopeId = 0)
    {
        $MCStoreId = Mage::getModel('mailchimp/config')->getMCStoreId($scope, $scopeId);
        if (!empty($MCStoreId)) {
            try {
                Mage::getModel('mailchimp/api_stores')->deleteStore($scope, $scopeId, $MCStoreId);
            } catch (Mailchimp_Error $e) {
                Mage::helper('mailchimp')->logError($e->getFriendlyMessage());
            }
            //clear store config values
            Mage::getConfig()->deleteConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_MCSTOREID, $scope, $scopeId);
        }
    }

    /**
     * Create merge fields configured on MailChimp remotely.
     *
     * @param $scope
     * @param $scopeId
     */
    public function createMergeFields($scope, $scopeId)
    {
        $listId = Mage::getModel('mailchimp/config')->getDefaultList($scope, $scopeId);
        $maps = unserialize(Mage::getModel('mailchimp/config')->getMapFields($scope, $scopeId));
        $customFieldTypes = unserialize(Mage::getModel('mailchimp/config')->getCustomMapFields($scope, $scopeId));
        $api = Mage::getModel('mailchimp/config')->getApi($scope, $scopeId);
        $mailchimpFields = array();
        if ($api) {
            try {
                $mailchimpFields = $api->lists->mergeFields->getAll($listId, null, null, 50);
            } catch (Mailchimp_Error $e) {
                Mage::helper('mailchimp')->logError($e->getFriendlyMessage());
            }
            if (count($mailchimpFields) > 0) {
                foreach ($maps as $map) {
                    $customAtt = $map['magento'];
                    $chimpTag = $map['mailchimp'];
                    $alreadyExists = false;
                    $created = false;
                    foreach ($mailchimpFields['merge_fields'] as $mailchimpField) {
                        if ($mailchimpField['tag'] == $chimpTag || strtoupper($chimpTag) == 'EMAIL') {
                            $alreadyExists = true;
                        }
                    }
                    if (!$alreadyExists) {
                        foreach ($customFieldTypes as $customFieldType) {
                            if ($customFieldType['value'] == $chimpTag) {
                                try {
                                    $api->lists->mergeFields->add($listId, $customFieldType['label'], $customFieldType['field_type'], null, $chimpTag);
                                } catch (Mailchimp_Error $e) {
                                    Mage::helper('mailchimp')->logError($e->getFriendlyMessage());
                                }
                                $created = true;
                            }
                        }
                        if (!$created) {
                            $attrSetId = Mage::getResourceModel('eav/entity_attribute_collection')
                                ->setEntityTypeFilter(1)
                                ->addSetInfo()
                                ->getData();
                            $label = null;
                            foreach ($attrSetId as $option) {
                                if ($option['attribute_id'] == $customAtt && $option['frontend_label']) {
                                    $label = $option['frontend_label'];
                                }
                            }
                            try {
                                if ($label) {
                                    //Shipping and Billing Address
                                    if ($customAtt == 13 || $customAtt == 14) {
                                        $api->lists->mergeFields->add($listId, $label, 'address', null, $chimpTag);
                                        //Birthday
                                    } elseif ($customAtt == 11) {
                                        $api->lists->mergeFields->add($listId, $label, 'date', null, $chimpTag);
                                    } else {
                                        $api->lists->mergeFields->add($listId, $label, 'text', null, $chimpTag);
                                    }
                                }
                            } catch (Mailchimp_Error $e) {
                                Mage::helper('mailchimp')->logError($e->getFriendlyMessage());
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Get date with Micro time to use on variable ids.
     * 
     * @return string
     */
    public function getDateMicrotime()
    {
        $microtime = explode(' ', microtime());
        $msec = $microtime[0];
        $msecArray = explode('.', $msec);
        $date = date('Y-m-d-H-i-s') . '-' . $msecArray[1];
        return $date;
    }

    /**
     * Get MailChimp API object.
     * 
     * @param $scope
     * @param $scopeId
     * @return Ebizmarts_Mailchimp|null
     */
    public function getApi($scope, $scopeId)
    {
        $apiKey = Mage::getModel('mailchimp/config')->getApiKey($scope, $scopeId);
        $api = null;
        if ($apiKey != null && $apiKey != "") {
            $api = new Ebizmarts_Mailchimp($apiKey, null, 'Mailchimp4Magento' . (string)Mage::getConfig()->getNode('modules/Ebizmarts_MailChimp/version'));
        }
        return $api;
    }

    /**
     * Get Store Id by list Id with config for path enabled if path given.
     * If multiple store ids found prioritizes default scopes.
     *
     * @param $listId
     * @param null $path
     * @return int|null|string
     */
    public function getStoreByListIdWithConfigEnabled($listId, $path = null)
    {
        $storeId = null;
        $auxStoreId = null;
        $magentoDefaultStoreId = null;
        $websiteDefaultStoreId = null;
        $stores = Mage::app()->getStores();
        foreach ($stores as $storeId => $store) {
            $listIdForStore = Mage::getModel('mailchimp/config')->getDefaultList('stores', $storeId);
            if ($listId == $listIdForStore) {
                if ($path) {
                    if (Mage::getModel('mailchimp/config')->getConfigValueForScope($path, 'stores', $storeId)) {
                        $auxStoreId = $storeId;
                    }
                } else {
                    $auxStoreId = $storeId;
                }
            }
            if ($auxStoreId) {
                if ($this->_isMagentoDefault($auxStoreId)) {
                    $magentoDefaultStoreId = $auxStoreId;
                } elseif ($this->_isWebsiteDefault($auxStoreId)) {
                    $websiteDefaultStoreId = $auxStoreId;
                }
            }
        }
        if ($magentoDefaultStoreId) {
            $storeId = $magentoDefaultStoreId;
        } elseif ($websiteDefaultStoreId) {
            $storeId = $websiteDefaultStoreId;
        } elseif ($auxStoreId) {
            $storeId = $auxStoreId;
        }
        return $storeId;
    }

    /**
     * Return if given store id is Magento's default store view id.
     *
     * @param $storeId
     * @return bool
     * @throws Mage_Core_Exception
     */
    protected function _isMagentoDefault($storeId)
    {
        $magentoDefaultStoreId = Mage::app()
            ->getWebsite(true)
            ->getDefaultGroup()
            ->getDefaultStoreId();
        if ($magentoDefaultStoreId == $storeId) {
            return true;
        }
        return false;
    }

    /**
     * Return if given store id is default for its website.
     *
     * @param $storeId
     * @return bool
     * @throws Mage_Core_Exception
     */
    protected function _isWebsiteDefault($storeId)
    {
        $websiteId = Mage::getModel('core/store')->load($storeId)->getWebsiteId();
        $websiteDefaultStoreId = Mage::app()
            ->getWebsite($websiteId)
            ->getDefaultStore()
            ->getId();
        if ($websiteDefaultStoreId == $storeId) {
            return true;
        }
        return false;
    }
}
