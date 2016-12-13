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

/**
 * Cron processor class
 *
 */
class Ebizmarts_MailChimp_Model_Cron
{

    public function syncBatchData($cron)
    {
        Mage::getModel('mailchimp/api_batches')->handleEcommerceBatches();
//        Mage::getModel('mailchimp/api_batches')->handleSubscriberBatches();
    }

    public function populateSyncTable($cron)
    {
        $idsArray = Mage::getModel('mailchimp/config')->getAllIds();
        //For each different MailChimp store set up, prepare Ecommerce data ready to be sent.
        foreach ($idsArray as $scope => $idArray) {
            foreach ($idArray as $storeIds) {
                //Populate mailchimp_customer_sync_data table
                $collection = Mage::getModel('customer/customer')->getCollection();
                $collection = Mage::helper('mailchimp')->addStoresToFilter($collection, $scope, $storeIds['magento']);
                $lastCustomerSentId = Mage::getModel('mailchimp/config')->getCustomerLastSentId($scope, $storeIds['magento']);
                if ($lastCustomerSentId) {
                    $collection->addFieldToFilter('entity_id', array('gt' => $lastCustomerSentId));
                }
                $collection->getSelect()->limit((Ebizmarts_MailChimp_Model_Api_Customers::BATCH_LIMIT * 2));
                $lastId = 0;
                foreach ($collection as $customer) {
                    $itemInCollection = Mage::getModel('mailchimp/customersyncdata')->getCollection()
                        ->addFieldToFilter('item_id', array('eq' => $customer->getId()))
                        ->addFieldToFilter('scope', array('eq' => $scope . '_' . $storeIds['magento']));
                    if (count($itemInCollection) == 0) {
                        Mage::getModel('mailchimp/customersyncdata')
                            ->setData('item_id', $customer->getId())
                            ->setScope($scope . '_' . $storeIds['magento'])
                            ->save();
                    }
                    $lastId = $customer->getId();
                }
                Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_CUSTOMER_LAST_ID, $lastId, $scope, $storeIds['magento']);

                //Populate mailchimp_product_sync_data table
                $collection = Mage::getModel('catalog/product')->getCollection();
                switch ($scope) {
                    case 'websites':
                        $collection->addWebsiteFilter($storeIds['magento']);
                        break;
                    case 'stores':
                        $collection->addStoreFilter($storeIds['magento']);
                        break;
                    default:
                        break;
                }

                $lastProductSentId = Mage::getModel('mailchimp/config')->getProductLastSentId($scope, $storeIds['magento']);
                if ($lastProductSentId) {
                    $collection->addFieldToFilter('entity_id', array('gt' => $lastProductSentId));
                }
                
                $collection->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));
                $collection->getSelect()->limit((Ebizmarts_MailChimp_Model_Api_Products::BATCH_LIMIT * 2));
                $lastId = 0;
                foreach ($collection as $product) {
                    $itemInCollection = Mage::getModel('mailchimp/productsyncdata')->getCollection()
                        ->addFieldToFilter('item_id', array('eq' => $product->getId()))
                        ->addFieldToFilter('scope', array('eq' => $scope . '_' . $storeIds['magento']));
                    if (count($itemInCollection) == 0) {
                        Mage::getModel('mailchimp/productsyncdata')
                            ->setData('item_id', $product->getId())
                            ->setScope($scope . '_' . $storeIds['magento'])
                            ->save();
                    }
                    $lastId = $product->getId();
                }
                Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_PRODUCT_LAST_ID, $lastId, $scope, $storeIds['magento']);

                //Populate mailchimp_order_sync_data table
                $collection = Mage::getModel('sales/order')->getCollection()
                    ->addFieldToFilter('state', array('eq' => 'complete'));
                $lastOrderSentId = Mage::getModel('mailchimp/config')->getOrderLastSentId($scope, $storeIds['magento']);
                if ($lastOrderSentId) {
                    $collection->addFieldToFilter('entity_id', array('gt' => $lastOrderSentId));
                }
                $collection = Mage::helper('mailchimp')->addStoresToFilter($collection, $scope, $storeIds['magento']);
                $collection->getSelect()->limit((Ebizmarts_MailChimp_Model_Api_Orders::BATCH_LIMIT * 2));
                $lastId = 0;
                foreach ($collection as $order) {
                    $itemInCollection = Mage::getModel('mailchimp/ordersyncdata')->getCollection()
                        ->addFieldToFilter('item_id', array('eq' => $order->getId()))
                        ->addFieldToFilter('scope', array('eq' => $scope . '_' . $storeIds['magento']));
                    if (count($itemInCollection) == 0) {
                        Mage::getModel('mailchimp/ordersyncdata')
                            ->setData('item_id', $order->getId())
                            ->setScope($scope . '_' . $storeIds['magento'])
                            ->save();
                    }
                    $lastId = $order->getId();
                }
                Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_ORDER_LAST_ID, $lastId, $scope, $storeIds['magento']);

                //Populate mailchimp_quote_sync_data table
                $collection = Mage::getModel('sales/quote')->getCollection();
                $lastQuoteSentId = Mage::getModel('mailchimp/config')->getQuoteLastSentId($scope, $storeIds['magento']);
                if ($lastQuoteSentId) {
                    $collection->addFieldToFilter('entity_id', array('gt' => $lastQuoteSentId));
                }
                $collection = Mage::helper('mailchimp')->addStoresToFilter($collection, $scope, $storeIds['magento']);
                $collection->getSelect()->limit((Ebizmarts_MailChimp_Model_Api_Carts::BATCH_LIMIT * 2));
                $lastId = 0;
                foreach ($collection as $quote) {
                    $itemInCollection = Mage::getModel('mailchimp/quotesyncdata')->getCollection()
                        ->addFieldToFilter('item_id', array('eq' => $quote->getId()))
                        ->addFieldToFilter('scope', array('eq' => $scope . '_' . $storeIds['magento']));
                    if (count($itemInCollection) == 0) {
                        Mage::getModel('mailchimp/quotesyncdata')
                            ->setData('item_id', $quote->getId())
                            ->setScope($scope . '_' . $storeIds['magento'])
                            ->save();
                    }
                    $lastId = $quote->getId();
                }
                Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_QUOTE_LAST_ID, $lastId, $scope, $storeIds['magento']);
            }
        }
        Mage::getConfig()->cleanCache();
    }
}