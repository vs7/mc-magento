<?php
/**
 * MailChimp For Magento
 *
 * @category Ebizmarts_MailChimp
 * @author Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @date: 5/19/16 3:55 PM
 * @file: ProdcessWebhook.php
 */
class Ebizmarts_MailChimp_Model_ProcessWebhook
{
    /**
     * Webhooks request url path
     *
     * @const string
     */

    const WEBHOOKS_PATH = 'mailchimp/webhook/index/';

    /**
     * Process Webhook request
     *
     * @param array $data
     * @return void
     */
    public function processWebhookData(array $data)
    {
        switch ($data['type']) {
            case 'subscribe':
                $this->_subscribe($data);
                break;
            case 'unsubscribe':
                $this->_unsubscribe($data);
                break;
            case 'cleaned':
                $this->_clean($data);
                break;
            case 'upemail':
                $this->_updateEmail($data);
                break;
        }
    }

    /**
     * Update customer email <upemail>
     *
     * @param array $data
     * @return void
     */
    protected function _updateEmail(array $data)
    {

        $old = $data['data']['old_email'];
        $new = $data['data']['new_email'];

        $oldSubscriber = Mage::getModel('newsletter/subscriber')
            ->loadByEmail($old);
        $newSubscriber = Mage::getModel('newsletter/subscriber')
            ->loadByEmail($new);

        if (!$newSubscriber->getId() && $oldSubscriber->getId()) {
            if (Mage::getModel('mailchimp/config')->getTwoWaySync('stores', $oldSubscriber->getStoreId())) {
                $oldSubscriber->setSubscriberEmail($new)
                    ->save();
            }
        } elseif (!$newSubscriber->getId() && !$oldSubscriber->getId()) {
            $storeId = Mage::helper('mailchimp')->getStoreByListIdWithConfigEnabled($data['data']['list_id'], Ebizmarts_MailChimp_Model_Config::GENERAL_TWO_WAY_SYNC);
            if ($storeId) {
                Mage::getModel('newsletter/subscriber')
                    ->setImportMode(TRUE)
                    ->setStoreId($storeId)
                    ->subscribe($new);
            }
        }
    }

    /**
     * Add "Cleaned Emails" notification to Adminnotification Inbox <cleaned>
     *
     * @param array $data
     * @return void
     */
    protected function _clean(array $data)
    {
        //Delete subscriber from Magento
        $subscriber = Mage::getModel('newsletter/subscriber')
            ->loadByEmail($data['data']['email']);

        if ($subscriber->getId() && Mage::getModel('mailchimp/config')->getTwoWaySync('stores', $subscriber->getStoreId())) {
            try {
                $subscriber->delete();
            } catch (Exception $e) {
                Mage::logException($e);
                Mage::helper('mailchimp')->logError('Webhook clean processing error: '. $e->getMessage());
            }
        }
    }

    /**
     * Subscribe email to Magento list, store aware
     *
     * @param array $data
     * @return void
     */
    protected function _subscribe(array $data)
    {
        try {
            $subscriber = Mage::getModel('newsletter/subscriber')
                ->loadByEmail($data['data']['email']);
            if ($subscriber->getId()) {
                $subscriber->setStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
                    ->save();
            } else {
                $storeId = Mage::helper('mailchimp')->getStoreByListIdWithConfigEnabled($data['data']['list_id']);
                $subscriber = Mage::getModel('newsletter/subscriber')
                    ->setStoreId($storeId)
                    ->setImportMode(TRUE);
                if (isset($data['data']['fname'])) {
                    $subscriberFname = filter_var($data['data']['fname'], FILTER_SANITIZE_STRING);
                    $subscriber->setSubscriberFirstname($subscriberFname);
                }
                if (isset($data['data']['lname'])) {
                    $subscriberLname = filter_var($data['data']['lname'], FILTER_SANITIZE_STRING);
                    $subscriber->setSubscriberLastname($subscriberLname);
                }
                $subscriber->subscribe($data['data']['email']);

            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::helper('mailchimp')->logError('Webhook subscribe processing error: '. $e->getMessage());
        }
    }

    /**
     * Unsubscribe or delete email from Magento list, store aware
     *
     * @param array $data
     * @return void
     */
    protected function _unsubscribe(array $data)
    {
        try {
            $subscriber = Mage::getModel('newsletter/subscriber')
                ->loadByEmail($data['data']['email']);

            if ($subscriber->getId() && Mage::getModel('mailchimp/config')->getTwoWaySync('stores', $subscriber->getStoreId())) {
                switch ($data['data']['action']) {
                    case 'delete':
                        //if config setting "Webhooks Delete action" is set as "Delete customer account"
                        if (Mage::getStoreConfig("mailchimp/general/webhook_delete") == 1) {
                            $subscriber->delete();
                        } else {
                            $subscriber->setImportMode(TRUE)->unsubscribe();
                        }
                        break;
                    case 'unsub':
                        $subscriber->setImportMode(TRUE)->unsubscribe();
                        break;
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::helper('mailchimp')->logError('Webhook unsubscribe processing error: '. $e->getMessage());
        }
    }

    protected function _profile(array $data)
    {
        $email = $data['data']['email'];
        $subscriber = Mage::getModel('newsletter/subscriber')
            ->loadByEmail($data['data']['email']);

        $customerCollection = Mage::getModel('customer/customer')->getCollection()
            ->addFieldToFilter('email', array('eq' => $email));
        if (count($customerCollection) > 0) {
            $customer = $customerCollection->getFirstItem();
            if (Mage::getModel('mailchimp/config')->getTwoWaySync('stores', $customer->getStoreId())) {
                Mage::getModel('mailchimp/api_customer')->setMergeVars($customer, $data['data']['merges']);
            }
        } else {
            if (Mage::getModel('mailchimp/config')->getTwoWaySync('stores', $subscriber->getStoreId())) {
                $subscriber->setSubscriberFirstname($data['data']['merges']['FNAME'])
                    ->setSubscriberLastname($data['data']['merges']['LNAME'])
                    ->save();
            }
        }
    }
}
