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
class Ebizmarts_MailChimp_Model_Api_Batches
{
    /**
     * Get Results and send Ecommerce Batches.
     */
    public function handleEcommerceBatches()
    {
        $idsArray = Mage::getModel('mailchimp/config')->getAllIds();
        foreach ($idsArray as $scope => $idArray) {
            foreach ($idArray as $storeIds) {
                $this->_getResults($scope, $storeIds['magento'], $storeIds['mailchimp']);
                $this->_sendEcommerceBatch($scope, $storeIds['magento'], $storeIds['mailchimp']);
            }
        }
    }

    /**
     * Get Results and send Subscriber Batches.
     */
    public function handleSubscriberBatches()
    {
        $this->_sendSubscriberBatches();
    }

    /**
     * Get results of batch operations sent to MailChimp.
     *
     * @param bool $isMailChimpStoreId
     */
    protected function _getResults($scope, $scopeId, $mailchimpStoreId = null)
    {
        $collection = Mage::getModel('mailchimp/syncbatches')->getCollection();
        if ($mailchimpStoreId) {
            $collection->addFieldToFilter('store_id', array('eq' => $mailchimpStoreId));
        } else {
            $collection = Mage::helper('mailchimp')->addStoresToFilter($collection, $scope, $scopeId);
        }
        $collection->addFieldToFilter('status', array('eq' => 'pending'));
        foreach ($collection as $item) {
            try {
                $files = $this->getBatchResponse($item->getBatchId(), $scope, $scopeId);
                if (count($files)) {
                    $this->processEachResponseFile($files, $item->getBatchId(), $scope, $scopeId);
                    $item->setStatus('completed');
                    $item->save();
                }
                $baseDir = Mage::getBaseDir();
                if (is_dir($baseDir . DS . 'var' . DS . 'mailchimp' . DS . $item->getBatchId())) {
                    rmdir($baseDir . DS . 'var' . DS . 'mailchimp' . DS . $item->getBatchId());
                }
            } catch (Exception $e) {
                Mage::log("Error with a response: " . $e->getMessage());
            }
        }
    }

    /**
     * Send Customers, Products, Orders, Carts to MailChimp store.
     *
     * @param $mailchimpStoreId
     * @return mixed|null
     */
    public function _sendEcommerceBatch($scope, $scopeId, $mailchimpStoreId)
    {
        try {
            if (Mage::getModel('mailchimp/config')->getMailChimpEnabled($scope, $scopeId) && Mage::helper('mailchimp')->isEcomSyncDataEnabled($scope, $scopeId)) {

                $batchArray = array();

                //customer operations
                $customersArray = Mage::getModel('mailchimp/api_customers')->createBatchJson($scope, $scopeId, $mailchimpStoreId);
                $batchArray['operations'] = $customersArray;
                //product operations
                $productsArray = Mage::getModel('mailchimp/api_products')->createBatchJson($scope, $scopeId, $mailchimpStoreId);
                $batchArray['operations'] = array_merge($batchArray['operations'], $productsArray);
                //cart operations
                $cartsArray = Mage::getModel('mailchimp/api_carts')->createBatchJson($scope, $scopeId, $mailchimpStoreId);
                $batchArray['operations'] = array_merge($batchArray['operations'], $cartsArray);
                //order operations
                $ordersArray = Mage::getModel('mailchimp/api_orders')->createBatchJson($scope, $scopeId, $mailchimpStoreId);
                $batchArray['operations'] = array_merge($batchArray['operations'], $ordersArray);
                if (empty($ordersArray)) {
                    $ordersCanceledArray = Mage::getModel('mailchimp/api_orders')->createCanceledBatchJson($scope, $scopeId, $mailchimpStoreId);
                    $batchArray['operations'] = array_merge($batchArray['operations'], $ordersCanceledArray);
                }
                try {
                    $mailchimpApi = Mage::helper('mailchimp')->getApi($scope, $scopeId);
                    if (!empty($batchArray['operations'])) {
                        $batchJson = json_encode($batchArray);
                        if (!$batchJson || $batchJson == '') {
                            Mage::helper('mailchimp')->logRequest('An empty operation was detected');
                        } else {
                            if (Mage::getModel('mailchimp/config')->getEcommerceReseted($scope, $scopeId)) {
                                Mage::helper('mailchimp')->resetMCEcommerceData(true, $scope, $scopeId);
                                Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_MCSTORE_RESETED, 0, $scope, $scopeId);
                                Mage::getConfig()->cleanCache();
                            } else {
                                $batchResponse = $mailchimpApi->batchOperation->add($batchJson);
                                Mage::helper('mailchimp')->logRequest($batchJson, $batchResponse['id']);
                                //save batch id to db
                                $batch = Mage::getModel('mailchimp/syncbatches');
                                $batch->setStoreId($mailchimpStoreId)
                                    ->setBatchId($batchResponse['id'])
                                    ->setStatus($batchResponse['status']);
                                $batch->save();
                                return $batchResponse;
                            }
                        }
                    } elseif (Mage::getModel('mailchimp/config')->getMCIsSyncing($scope, $scopeId)) {
                        $isSyncing = false;
                        $mailchimpApi->ecommerce->stores->edit($mailchimpStoreId, $isSyncing);
                        Mage::getConfig()->saveConfig(Ebizmarts_MailChimp_Model_Config::GENERAL_MCISSYNCING, 0, $scope, $scopeId);
                        Mage::getConfig()->cleanCache();
                    }
                } catch (Mailchimp_Error $e) {
                    Mage::helper('mailchimp')->logError($e->getFriendlyMessage());
                } catch (Exception $e) {
                    Mage::log("Json encode fails");
                    Mage::log($batchArray);
                }
            }

        } catch (Mailchimp_Error $e) {
            Mage::helper('mailchimp')->logError($e->getFriendlyMessage());

        } catch (Exception $e) {
            Mage::helper('mailchimp')->logError($e->getMessage());
        }
        return null;
    }

    /**
     * Send Subscribers batch on each store view, return array of batches responses.
     *
     * @return array
     */
    protected function _sendSubscriberBatches()
    {
        $subscriberLimit = Ebizmarts_MailChimp_Model_Api_subscribers::BATCH_LIMIT;
        $stores = Mage::app()->getStores();
        $batchResponses = array();
        foreach ($stores as $store) {
            $storeId = $store->getId();
            $this->_getResults('stores', $storeId);
            if ($subscriberLimit > 0) {
                list($batchResponses[], $subscriberLimit) = $this->sendStoreSubscriberBatch($storeId, $subscriberLimit);
            } else {
                break;
            }
        }
        return $batchResponses;
    }

    /**
     * Send Subscribers batch on particular store view, return batch response.
     *
     * @param $storeId
     * @param $limit
     * @return array|null
     */
    public function sendStoreSubscriberBatch($storeId, $limit)
    {
        try {
            $subscribersArray = array();
            if (Mage::getModel('mailchimp/config')->getMailChimpEnabled('stores', $storeId)) {
                $listId = Mage::getModel('mailchimp/config')->getDefaultList('stores', $storeId);

                $batchArray = array();

                //subscriber operations
                $subscribersArray = Mage::getModel('mailchimp/api_subscribers')->createBatchJson($listId, $storeId, $limit);
                $limit -= count($subscribersArray);
            }

            $batchArray['operations'] = $subscribersArray;

            if (!empty($batchArray['operations'])) {
                $batchJson = json_encode($batchArray);
                if (!$batchJson || $batchJson == '') {
                    Mage::helper('mailchimp')->logRequest('An empty operation was detected');
                } else {
                    $mailchimpApi = Mage::helper('mailchimp')->getApi('stores', $storeId);
                    $batchResponse = $mailchimpApi->batchOperation->add($batchJson);
                    Mage::helper('mailchimp')->logRequest($batchJson, $batchResponse['id']);

                    //save batch id to db
                    $batch = Mage::getModel('mailchimp/syncbatches');
                    $batch->setStoreId($storeId)
                        ->setBatchId($batchResponse['id'])
                        ->setStatus($batchResponse['status']);
                    $batch->save();
                    return array($batchResponse, $limit);
                }
            }
        } catch (Mailchimp_Error $e) {
            Mage::helper('mailchimp')->logError($e->getFriendlyMessage());

        } catch (Exception $e) {
            Mage::helper('mailchimp')->logError($e->getMessage());
        }
        return null;
    }

    /**
     * @param $batchId
     * @return array
     */
    protected function getBatchResponse($batchId, $scope, $scopeId)
    {
        $files = array();
        try {
            $baseDir = Mage::getBaseDir();
            $api = Mage::helper('mailchimp')->getApi($scope, $scopeId);
            // check the status of the job
            $response = $api->batchOperation->status($batchId);
            Mage::log($response, null, 'response.log', true);
            if (isset($response['status']) && $response['status'] == 'finished') {
                // get the tar.gz file with the results
                $fileUrl = urldecode($response['response_body_url']);
                $fileName = $baseDir . DS . 'var' . DS . 'mailchimp' . DS . $batchId;
                $fd = fopen($fileName . '.tar.gz', 'w');
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fileUrl);
                curl_setopt($ch, CURLOPT_FILE, $fd);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // this will follow redirects
                $r = curl_exec($ch);
                curl_close($ch);
                fclose($fd);
                mkdir($baseDir . DS . 'var' . DS . 'mailchimp' . DS . $batchId);
                $archive = new Mage_Archive();
                $archive->unpack($fileName . '.tar.gz', $baseDir . DS . 'var' . DS . 'mailchimp' . DS . $batchId);
                $archive->unpack($baseDir . DS . 'var' . DS . 'mailchimp' . DS . $batchId . '/' . $batchId . '.tar', $baseDir . DS . 'var' . DS . 'mailchimp' . DS . $batchId);
                $dir = scandir($baseDir . DS . 'var' . DS . 'mailchimp' . DS . $batchId);
                foreach ($dir as $d) {
                    $name = pathinfo($d);
                    if ($name['extension'] == 'json') {
                        $files[] = $baseDir . DS . 'var' . DS . 'mailchimp' . DS . $batchId . '/' . $d;
                    }
                }
                unlink($baseDir . DS . 'var' . DS . 'mailchimp' . DS . $batchId . '/' . $batchId . '.tar');
                unlink($fileName . '.tar.gz');
            }
        } catch (Mailchimp_Error $e) {
            Mage::helper('mailchimp')->logError($e->getFriendlyMessage());
        } catch (Exception $e) {
            Mage::helper('mailchimp')->logError($e->getMessage());
        }
        return $files;
    }

    /**
     * @param $files
     * @param $batchId
     */
    protected function processEachResponseFile($files, $batchId, $scope, $scopeId)
    {
        foreach ($files as $file) {
            $items = json_decode(file_get_contents($file));
            foreach ($items as $item) {
                if ($item->status_code != 200) {
                    $line = explode('_', $item->operation_id);
                    $type = $line[0];
                    $id = $line[2];

                    $mailchimpErrors = Mage::getModel('mailchimp/mailchimperrors');

                    //parse error
                    $response = json_decode($item->response);
                    $errorDetails = "";
                    if (!empty($response->errors)) {
                        foreach ($response->errors as $error) {
                            if (isset($error->field) && isset($error->message)) {
                                $errorDetails .= $errorDetails != "" ? " / " : "";
                                $errorDetails .= $error->field . " : " . $error->message;
                            }
                        }
                    }
                    if ($errorDetails == "") {
                        $errorDetails = $response->detail;
                    }

                    $error = $errorDetails . ' Scope ' . $scope . ' ' . $scopeId;

                    switch ($type) {
                        case Ebizmarts_MailChimp_Model_Config::IS_PRODUCT:
                            $p = Mage::getModel('mailchimp/productsyncdata')->getCollection()
                                ->addFieldToFilter('scope', array('eq' => $scope . '_' . $scopeId))
                                ->addFieldToFilter('item_id', array('eq' => $id))
                                ->getFirstItem();
                            if ($p->getId()) {
                                $p->setData("mailchimp_sync_error", $p->getData("mailchimp_sync_error") . "\n" . $error);
                                $p->save();
                            } else {
                                Mage::helper('mailchimp')->logError("Error: product " . $id . " not found for " . $scope . "_" . $scopeId);
                            }
                            break;
                        case Ebizmarts_MailChimp_Model_Config::IS_CUSTOMER:
                            $c = Mage::getModel('mailchimp/customersyncdata')->getCollection()
                                ->addFieldToFilter('scope', array('eq' => $scope . '_' . $scopeId))
                                ->addFieldToFilter('item_id', array('eq' => $id))
                                ->getFirstItem();
                            if ($c->getId()) {
                                $c->setData("mailchimp_sync_error", $c->getData("mailchimp_sync_error") . "\n" . $error);
                                $c->save();
                            } else {
                                Mage::helper('mailchimp')->logError("Error: customer " . $id . " not found for \" . $scope . \"_\" . $scopeId");
                            }
                            break;
                        case Ebizmarts_MailChimp_Model_Config::IS_ORDER:
                            $o = Mage::getModel('mailchimp/ordersyncdata')->getCollection()
                                ->addFieldToFilter('scope', array('eq' => $scope . '_' . $scopeId))
                                ->addFieldToFilter('item_id', array('eq' => $id))
                                ->getFirstItem();
                            if ($o->getId()) {
                                $o->setData("mailchimp_sync_error", $o->getData("mailchimp_sync_error") . "\n" . $error);
                                $o->save();
                            } else {
                                Mage::helper('mailchimp')->logError("Error: order " . $id . " not found for \" . $scope . \"_\" . $scopeId");
                            }
                            break;
                        case Ebizmarts_MailChimp_Model_Config::IS_QUOTE:
                            $q = Mage::getModel('mailchimp/quotesyncdata')->getCollection()
                                ->addFieldToFilter('scope', array('eq' => $scope . '_' . $scopeId))
                                ->addFieldToFilter('item_id', array('eq' => $id))
                                ->getFirstItem();
                            if ($q->getId()) {
                                $q->setData("mailchimp_sync_error", $q->getData("mailchimp_sync_error") . "\n" . $error);
                                $q->save();
                            } else {
                                Mage::helper('mailchimp')->logError("Error: quote " . $id . " not found for \" . $scope . \"_\" . $scopeId");
                            }
                            break;
                        case Ebizmarts_MailChimp_Model_Config::IS_SUBSCRIBER:
                            $s = Mage::getModel('newsletter/subscriber')->load($id);
                            if ($s->getId() == $id) {
                                $s->setData("mailchimp_sync_error", $s->getData("mailchimp_sync_error") . "\n" . $error);
                                $s->save();
                            } else {
                                Mage::helper('mailchimp')->logError("Error: subscriber " . $id . " not found for \" . $scope . \"_\" . $scopeId");
                            }
                            break;
                        default:
                            Mage::helper('mailchimp')->logError("Error: no identification " . $type . " found for \" . $scope . \"_\" . $scopeId");
                            break;
                    }
                    $mailchimpErrors->setType($response->type);
                    $mailchimpErrors->setTitle($response->title);
                    $mailchimpErrors->setStatus($item->status_code);
                    $mailchimpErrors->setErrors($errorDetails);
                    $mailchimpErrors->setRegtype($type);
                    $mailchimpErrors->setOriginalId($id);
                    $mailchimpErrors->setBatchId($batchId);
                    $mailchimpErrors->save();
                    Mage::helper('mailchimp')->logError($error);
                }
            }
            unlink($file);
        }
    }
}