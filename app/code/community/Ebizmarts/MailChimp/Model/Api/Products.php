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
class Ebizmarts_MailChimp_Model_Api_Products
{

    const BATCH_LIMIT = 100;

    public function createBatchJson($scope, $scopeId, $mailchimpStoreId)
    {
        $productArray = array();
        //get customers
        $customerTable = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity');
        $collection = Mage::getModel('mailchimp/productsyncdata')->getCollection()
            ->addFieldToFilter(
                array(
                    'mailchimp_sync_delta',
                    'mailchimp_sync_modified',
                ),
                array(
                    array('lt' => Mage::getModel('mailchimp/config')->getMCMinSyncDateFlag($scope, $scopeId)),
                    array('eq' => 1)
                )
            )
            ->addFieldToFilter('scope', array('eq' => $scope . '_' . $scopeId));

        $joinCondition = 'p.entity_id = item_id';

        $collection->getSelect()
            ->join(array('p' => $customerTable), $joinCondition, array('p.entity_id'))
            ->limit(self::BATCH_LIMIT);
        $batchId = Ebizmarts_MailChimp_Model_Config::IS_PRODUCT . '_' . Mage::helper('mailchimp')->getDateMicrotime();
        $counter = 0;
        foreach ($collection as $item) {
            $syncData = Mage::getModel('mailchimp/productsyncdata')->load($item->getId());
            $product = Mage::getModel('catalog/product')->load($item->getItemId());
            $product->getTierPrice();
            //define variants and root products
            if ($item->getMailchimpSyncModified() && $item->getMailchimpSyncDelta() && $item->getMailchimpSyncDelta() > Mage::getModel('mailchimp/config')->getMCMinSyncDateFlag($scope, $scopeId)) {
                $productArray = array_merge($this->_buildOldProductRequest($product, $batchId, $scope, $scopeId, $mailchimpStoreId), $productArray);
                $counter = (count($productArray));
                $syncData->setData("mailchimp_sync_delta", Varien_Date::now())
                    ->setData("mailchimp_sync_error", "")
                    ->setData('mailchimp_sync_modified', 0)
                    ->save();
                continue;
            } else {
                $data = $this->_buildNewProductRequest($product, $batchId, $mailchimpStoreId);
            }

            if (!empty($data)) {
                $productArray[$counter] = $data;
                $counter++;

                //update product delta
                $syncData->setData("mailchimp_sync_delta", Varien_Date::now())
                    ->setData("mailchimp_sync_error", "")
                    ->setData('mailchimp_sync_modified', 0)
                    ->save();
            } else {
                $syncData->setData("mailchimp_sync_delta", Varien_Date::now())
                    ->setData("mailchimp_sync_error", "This product type is not supported on MailChimp.")
                    ->setData('mailchimp_sync_modified', 0)
                    ->save();
                continue;
            }
        }
        return $productArray;
    }

    protected function _buildNewProductRequest($product,$batchId,$mailchimpStoreId)
    {
        $variantProducts = array();
        if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
            $variantProducts[] = $product;
        } else if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            //get children
            $childProducts = Mage::getModel('catalog/product_type_configurable')->getChildrenIds($product->getId());

            //add itself as variant
            $variantProducts[] = $product;
            if (count($childProducts[0])) {
                foreach ($childProducts[0] as $childId) {
                    $variantProducts[] = Mage::getModel('catalog/product')->load($childId);
                }
            }
        } else if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL || $product->getTypeId() == "downloadable") {
            $variantProducts = array();
            $variantProducts[] = $product;
        } else {
            // don't need to send the grouped products
            //@toDo bundle
            return array();
        }

        $bodyData = $this->_buildProductData($product, false, $variantProducts);
        try {
            $body = json_encode($bodyData);

        } catch (Exception $e) {
            //json encode failed
            Mage::helper('mailchimp')->logError("Product " . $product->getId() . " json encode failed");
            return array();
        }
        $data = array();
        $data['method'] = "POST";
        $data['path'] = "/ecommerce/stores/" . $mailchimpStoreId . "/products";
        $data['operation_id'] = $batchId . '_' . $product->getId();
        $data['body'] = $body;
        return $data;
    }

    protected function _buildOldProductRequest($product, $batchId, $scope, $scopeId, $mailchimpStoreId)
    {
        $operations = array();
        if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE || $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL || $product->getTypeId() == "downloadable") {
            $data = $this->_buildProductData($product);

            $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());

            if (empty($parentIds)) {
                $parentIds = array($product->getId());
            }

            //add or update variant
            foreach ($parentIds as $parentId) {
                $parent = $this->loadByScopeAndId($scope, $scopeId, $parentId);
                if ($parent && $parent->getMailchimpSyncDelta() > Mage::getModel('mailchimp/config')->getMCMinSyncDateFlag($scope, $scopeId)) {
                    $variendata = array();
                    $variendata["id"] = $data["id"];
                    $variendata["title"] = $data["title"];
                    $variendata["url"] = $data["url"];
                    $variendata["sku"] = $data["sku"];
                    $variendata["price"] = $data["price"];
                    $variendata["inventory_quantity"] = $data["inventory_quantity"];
                    $variendata["image_url"] = $data["image_url"];
                    $variendata["backorders"] = $data["backorders"];
                    $variendata["visibility"] = $data["visibility"];
                    $productdata = array();
                    $productdata['method'] = "PUT";
                    $productdata['path'] = "/ecommerce/stores/" . $mailchimpStoreId . "/products/" . $parentId . '/variants/' . $data['id'];
                    $productdata['operation_id'] = $batchId . '_' . $parentId;
                    try {
                        $body = json_encode($variendata);

                    } catch (Exception $e) {
                        //json encode failed
                        Mage::helper('mailchimp')->logError("Product " . $product->getId() . " json encode failed");
                        continue;
                    }
                    $productdata['body'] = $body;
                    $operations[] = $productdata;
                }
            }
        }
        return $operations;
    }
    protected function _buildProductData($product, $isVarient = true, $variants = null)
    {
        $data = array();

        //data applied for both root and varient products
        $data["id"] = $product->getId();
        $data["title"] = $product->getName();
        $data["url"] = $product->getProductUrl();

        //image
        $productMediaConfig = Mage::getModel('catalog/product_media_config');
        $data["image_url"] = $productMediaConfig->getMediaUrl($product->getImage());

        //missing data
        $data["published_at_foreign"] = "";

        if ($isVarient) {
            //this is for a varient product
            $data["sku"] = $product->getSku();
            $data["price"] = $product->getPrice();

            //stock
            $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
            $data["inventory_quantity"] = (int)$stock->getQty();
            $data["backorders"] = (string)$stock->getBackorders();

            $data["visibility"] = $product->getVisibility();

        } else {
            //this is for a root product
            if($product->getDescription()) {
                $data["description"] = $product->getDescription();
            }

            //mailchimp product type (magento category)
            $categoryIds = $product->getCategoryIds();
            if (count($categoryIds)) {
                $category = Mage::getModel('catalog/category')->load($categoryIds[0]);
                $data["type"] = $category->getName();
            }

            //missing data
            $data["vendor"] = "";
            $data["handle"] = "";

            //variants
            $data["variants"] = array();
            foreach ($variants as $variant) {
                $data["variants"][] = $this->_buildProductData($variant);
            }
        }

        return $data;
    }
    public function update($productId)
    {
        $collection = Mage::getModel('mailchimp/productsyncdata')->getCollection()
            ->addFieldToFilter('item_id', array($productId));
        foreach ($collection as $item) {
            $item->setData("mailchimp_sync_delta", Varien_Date::now())
                ->setData("mailchimp_sync_error", "")
                ->setData("mailchimp_sync_modified", 1)
                ->save();
        }
    }
    public function sendModifiedProduct($order, $scope, $scopeId, $mailchimpStoreId)
    {
        $data = array();
        $batchId = Ebizmarts_MailChimp_Model_Config::IS_PRODUCT . '_' . Mage::helper('mailchimp')->getDateMicrotime();
        $items = $order->getAllVisibleItems();
        foreach ($items as $item) {
            $productId = $item->getProductId();
            $product = Mage::getModel('catalog/product')->load($productId);
            $productSyncData = $this->loadByScopeAndId($scope, $scopeId, $productId);
            if ($productSyncData) {
                if ($product->getId() != $item->getProductId() || $product->getTypeId() == 'bundle' || $product->getTypeId() == 'grouped') {
                    continue;
                }
                if ($productSyncData->getMailchimpSyncDelta() > Mage::getModel('mailchimp/config')->getMCMinSyncDateFlag($scope, $scopeId)) {
                    $data = $this->_buildOldProductRequest($product, $batchId, $scope, $scopeId, $mailchimpStoreId);
                    $this->_updateProduct($product, $scope, $scopeId);
                } elseif ($productSyncData->getMailchimpSyncDelta() < Mage::getModel('mailchimp/config')->getMCMinSyncDateFlag($scope, $scopeId)) {
                    $data = array($this->_buildNewProductRequest($product, $batchId, $mailchimpStoreId));
                    $this->_updateProduct($product, $scope, $scopeId);
                }
            }
        }
        return $data;
    }

    public function loadByScopeAndId($scope, $scopeId, $productId) {
        $syncData = null;
        $productMailChimpData = Mage::getModel('mailchimp/productsyncdata')->getCollection()
            ->addFieldToFilter('scope', array('eq' => $scope . '_' . $scopeId))
            ->addFieldToFilter('item_id', array('eq' => $productId));
        if (count($productMailChimpData)) {
            $syncData = $productMailChimpData->getFirstItem();
        }
        return $syncData;
    }

    protected function _updateProduct($product, $scope, $scopeId)
    {
        $productSyncData = $this->loadByScopeAndId($scope, $scopeId, $product->getId());
        if ($productSyncData) {
            $productSyncData->setData("mailchimp_sync_delta", Varien_Date::now())
                ->setData("mailchimp_sync_error", "")
                ->setData('mailchimp_sync_modified', 0)
                ->save();
        }
    }

}