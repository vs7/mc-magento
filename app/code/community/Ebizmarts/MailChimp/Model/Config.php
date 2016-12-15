<?php
/**
 * MailChimp For Magento
 *
 * @category Ebizmarts_MailChimp
 * @author Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @date: 4/29/16 3:55 PM
 * @file: Config.php
 */
class Ebizmarts_MailChimp_Model_Config
{
    const GENERAL_ACTIVE                = 'mailchimp/general/active';
    const GENERAL_APIKEY                = 'mailchimp/general/apikey';
    const GENERAL_OAUTH_WIZARD          = 'mailchimp/general/oauth_wizard';
    const GENERAL_ACCOUNT_DETAILS       = 'mailchimp/general/account_details';
    const GENERAL_LIST                  = 'mailchimp/general/list';
    const GENERAL_OLD_LIST              = 'mailchimp/general/old_list';
    const GENERAL_LIST_CHANGED_SCOPES   = 'mailchimp/general/list_changed_scopes';
    const GENERAL_MCSTOREID             = 'mailchimp/general/storeid';
    const GENERAL_MCISSYNCING           = 'mailchimp/general/is_syicing';
    const GENERAL_MCMINSYNCDATEFLAG     = 'mailchimp/general/mcminsyncdateflag';
    const GENERAL_MCSTORE_RESETED       = 'mailchimp/general/mcstore_reset';
    const GENERAL_SUB_MCMINSYNCDATEFLAG = 'mailchimp/general/sub_mcminsyncdateflag';
    const GENERAL_TWO_WAY_SYNC          = 'mailchimp/general/webhook_active';
    const GENERAL_UNSUBSCRIBE           = 'mailchimp/general/webhook_delete';
    const GENERAL_LOG                   = 'mailchimp/general/enable_log';
    const GENERAL_MAP_FIELDS            = 'mailchimp/general/map_fields';
    const GENERAL_CUSTOM_MAP_FIELDS     = 'mailchimp/general/customer_map_fields';
    const GENERAL_CUSTOMER_LAST_ID      = 'mailchimp/general/customer_last_id';
    const GENERAL_ORDER_LAST_ID         = 'mailchimp/general/order_last_id';
    const GENERAL_PRODUCT_LAST_ID       = 'mailchimp/general/product_last_id';
    const GENERAL_QUOTE_LAST_ID         = 'mailchimp/general/quote_last_id';

    const ECOMMERCE_ACTIVE              = 'mailchimp/ecommerce/active';
    const ECOMMERCE_CUSTOMERS_OPTIN     = 'mailchimp/ecommerce/customers_optin';

    const ENABLE_POPUP                  = 'mailchimp/emailcatcher/popup_general';
    const POPUP_HEADING                 = 'mailchimp/emailcatcher/popup_heading';
    const POPUP_TEXT                    = 'mailchimp/emailcatcher/popup_text';
    const POPUP_FNAME                   = 'mailchimp/emailcatcher/popup_fname';
    const POPUP_LNAME                   = 'mailchimp/emailcatcher/popup_lname';
    const POPUP_WIDTH                   = 'mailchimp/emailcatcher/popup_width';
    const POPUP_HEIGHT                  = 'mailchimp/emailcatcher/popup_height';
    const POPUP_SUBSCRIPTION            = 'mailchimp/emailcatcher/popup_subscription';
    const POPUP_CAN_CANCEL              = 'mailchimp/emailcatcher/popup_cancel';
    const POPUP_COOKIE_TIME             = 'mailchimp/emailcatcher/popup_cookie_time';
    const POPUP_INSIST                  = 'mailchimp/emailcatcher/popup_insist';

    const ABANDONEDCART_ACTIVE      = 'mailchimp/abandonedcart/active';
    const ABANDONEDCART_FIRSTDATE   = 'mailchimp/abandonedcart/firstdate';
    const ABANDONEDCART_PAGE        = 'mailchimp/abandonedcart/page';

    const WARNING_MESSAGE           = 'mailchimp/warning_message';
    const POPUP_MESSAGE             = 'mailchimp/popup_message';
    const RESET_MESSAGE             = 'mailchimp/reset_message';

    const MANDRILL_APIKEY           = 'mandrill/general/apikey';
    const MANDRILL_ACTIVE           = 'mandrill/general/active';
    const MANDRILL_LOG              = 'mandrill/general/enable_log';

    const IS_CUSTOMER   = "CUS";
    const IS_PRODUCT    = "PRO";
    const IS_ORDER      = "ORD";
    const IS_QUOTE      = "QUO";
    const IS_SUBSCRIBER = "SUB";

    /**
     *  Get configuration value from back end and front end unless storeId is sent, in this last case it gets the configuration from the store Id sent
     *
     * @param $path
     * @param null $storeId If this is null it gets the config for the current store (works for back end and front end)
     * @param bool $returnParentValueIfNull
     * @return mixed|null
     * @throws Mage_Core_Exception
     */
    public function getConfigValue($path, $storeId = null, $returnParentValueIfNull = false)
    {
        $scopeArray = array();
        $configValue = null;

        //Get store scope for back end or front end
        if (!$storeId) {
            $scopeArray = Mage::helper('mailchimp')->getConfigScopeId();
        } else {
            $scopeArray['storeId'] = $storeId;
        }
        if (!$returnParentValueIfNull) {
            if (isset($scopeArray['websiteId']) && $scopeArray['websiteId']) {
                //Website scope
                if (Mage::app()->getWebsite($scopeArray['websiteId'])->getConfig($path) !== null) {
                    $configValue = Mage::app()->getWebsite($scopeArray['websiteId'])->getConfig($path);
                }
            } elseif (isset($scopeArray['storeId']) && $scopeArray['storeId']) {
                //Store view scope
                if (Mage::getStoreConfig($path, $scopeArray['storeId']) !== null) {
                    $configValue = Mage::getStoreConfig($path, $scopeArray['storeId']);
                }
            } else {
                //Default config scope
                if (Mage::getStoreConfig($path) !== null) {
                    $configValue = Mage::getStoreConfig($path);
                }
            }
        }
        return $configValue;
    }

    /**
     * Get Config value for certain scope.
     *
     * @param $path
     * @param $scope
     * @param $scopeId
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function getConfigValueForScope($path, $scope, $scopeId)
    {
        if ($scope == 'websites') {
            $configValue = Mage::app()->getWebsite($scopeId)->getConfig($path);
        } else {
            $configValue = Mage::getStoreConfig($path, $scopeId);
        }
        return $configValue;
    }

    /**
     * Get local store_id value of the MC store.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getMCStoreId($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_MCSTOREID, $scope, $scopeId);
    }

    /**
     * Get MailChimp store id if store created for that particular scope. Will not return inherited values.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed|null
     */
    public function getMCStoreIdForScope($scope, $scopeId)
    {
        $collection = Mage::getModel('core/config_data')->getCollection()
            ->addFieldToFilter('path', array('eq' => Ebizmarts_MailChimp_Model_Config::GENERAL_MCSTOREID))
            ->addFieldToFilter('scope', array('eq' => $scope))
            ->addFieldToFilter('scope_id', array('eq' => $scopeId));
        if (count($collection)) {
            $config = $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_MCSTOREID, $scope, $scopeId);
        } else {
            $config = null;
        }

        return $config;
    }

    /**
     * Get value to decide whether to subscribe Customers or not.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getCustomerOptIn($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::ECOMMERCE_CUSTOMERS_OPTIN, $scope, $scopeId);
    }

    /**
     * Get Magento and MailChimp store ids for all the scopes with MailChimp stores associated to them.
     *
     * @return array
     */
    public function getAllIds()
    {
        $stores = Mage::app()->getStores();
        $MCStoreIds = array();
        $MCStoreIds['stores'] = array();
        $MCStoreIds['websites'] = array();
        $MCStoreIds['default'] = array();
        foreach ($stores as $store) {
            $MCStoreId = $this->getMCStoreIdForScope('stores', $store->getId());
            if ($MCStoreId) {
                $MCStoreIds['stores'][] = array('magento' => $store->getId(), 'mailchimp' => $MCStoreId);
            }
        }
        $websites = Mage::app()->getWebsites();
        foreach ($websites as $website) {
            $MCStoreId = $this->getMCStoreIdForScope('websites', $website->getId());
            if ($MCStoreId) {
                $MCStoreIds['websites'][] = array('magento' => $website->getId(), 'mailchimp' => $MCStoreId);
            }
        }
        $MCStoreId = $this->getMCStoreIdForScope('default', 0);
        if ($MCStoreId) {
            $MCStoreIds['default'][] = array('magento' => 0, 'mailchimp' => $MCStoreId);
        }
        return $MCStoreIds;
    }

    /**
     * Get local is_syncing value of the MC store.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getMCIsSyncing($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_MCISSYNCING, $scope, $scopeId);
    }

    /**
     * Minimum date for which ecommerce data needs to be re-uploaded.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getMCMinSyncDateFlag($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_MCMINSYNCDATEFLAG, $scope, $scopeId);
    }

    /**
     * Get if module is enabled in certain scope.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getMailChimpEnabled($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_ACTIVE, $scope, $scopeId);
    }

    /**
     * Get selected list in certain scope.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getDefaultList($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_LIST, $scope, $scopeId);
    }

    /**
     * Get if Ecommerce data is marked to be sent in certain scope.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getEcommerceEnabled($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::ECOMMERCE_ACTIVE, $scope, $scopeId);
    }

    /**
     * Get date to start sending carts after it.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getCartFirstDate($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::ABANDONEDCART_FIRSTDATE, $scope, $scopeId);
    }

    /**
     * Get if Carts should be sent to MailChimp.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getAbandonedCartEnabled($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::ABANDONEDCART_ACTIVE, $scope, $scopeId);
    }

    /**
     * Get if Ecommerce Data was reseted to prevent marking products as sent for an old MailChimp store.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getEcommerceReseted($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_MCSTORE_RESETED, $scope, $scopeId);
    }

    /**
     * Get API Key for scope.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getApiKey($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_APIKEY, $scope, $scopeId);
    }

    /**
     * Get Last Id populated to the mailchimp_customer_sync_data table.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getCustomerLastSentId($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_CUSTOMER_LAST_ID, $scope, $scopeId);
    }

    /**
     * Get Last Id populated to the mailchimp_product_sync_data table.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getProductLastSentId($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_PRODUCT_LAST_ID, $scope, $scopeId);
    }

    /**
     * Get Last Id populated to the mailchimp_order_sync_data table.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getOrderLastSentId($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_ORDER_LAST_ID, $scope, $scopeId);
    }

    /**
     * Get Last Id populated to the mailchimp_quote_sync_data table.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getQuoteLastSentId($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_QUOTE_LAST_ID, $scope, $scopeId);
    }

    /**
     * Get fields to be mapped to the MailChimp list.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getMapFields($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_MAP_FIELDS, $scope, $scopeId);
    }

    /**
     * Get all existing custom fields.
     *
     * @return mixed
     */
    public function getCustomMapFields()
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_CUSTOM_MAP_FIELDS, 'default', 0);
    }

    /**
     * Get flag for MailChimp -> Magento synchronization.
     * 
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getTwoWaySync($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::GENERAL_TWO_WAY_SYNC, $scope, $scopeId);
    }

    /**
     * Get if Email Catcher popup is enabled.
     *
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getPopupEnabled($scope, $scopeId)
    {
        return $this->getConfigValueForScope(Ebizmarts_MailChimp_Model_Config::ENABLE_POPUP, $scope, $scopeId);
    }
}