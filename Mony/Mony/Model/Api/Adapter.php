<?php
/**
 * @package   Mony_Mony
 * @author    Mony payments <support@monypayments.com>
 * @copyright Copyright (c) 2015-2016 Mony payments (http://www.monypayments.com)
 */

/**
 * Class Mony_Mony_Model_Api_Adapter
 *
 * Building API requests and parsing API responses.
 */
namespace Mony\Mony\Model\Api;

use \Magento\Checkout\Model\Session as CheckoutSession;
use \Mony\Mony\Model\Config\Mony as MonyConfig;

class Adapter extends Mony
{

    /* Customer method code */
    const CUSTOMER_METHOD_GET    = 'get';
    const CUSTOMER_METHOD_SEARCH = 'list';
    const CUSTOMER_METHOD_DELETE = 'delete';    

    const CUSTOMER_DELETE_STATUS_OK = 204;

    /**
     * @var CheckoutSession $checkoutSession
     */
    protected $checkoutSession;

    /**
     * @var MonyConfig $monyConfig
     */
    protected $monyConfig;


    public function __construct(
        CheckoutSession $checkoutSession,
        MonyConfig $monyConfig
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->monyConfig = $monyConfig;
    }

    /**
     * Get Charge amount for API
     *
     * @param $order
     * @param $amount
     * @return array
     */
    public function getChargeAmount($order, $amount)
    {
        return array(
            'amount' => $amount,
            'currency' => $order->getOrderCurrencyCode()
            );
    }

    /**
     * Get Payment Method for API
     *
     * @param $payment
     * @return array
     */
    public function getPaymentMethod($payment)
    {
        $order = $payment->getOrder();
        $shipping = $order->getShippingAddress();
        // $billing = $order->getBillingAddress();

        // Payment method data
        // we use shipping instead of billing because they should be the same in Magento 2
        $data =  array(
            // 'token' => $order->getPayment()->getMonypaymentsToken(),
            'token' => $this->checkoutSession->getData( \Mony\Mony\Controller\Payment\Process::ADDITIONAL_INFORMATION_KEY_MONY_TOKEN ),
            'billingAddress' => array(
                'name'  => $shipping->getName(),
                'address1'  => array_values($shipping->getStreet())[0],
                'suburb'    => $shipping->getCity(),
                'state'     => $shipping->getRegion(),
                'postcode'  => $shipping->getPostcode(),
                'countryCode' => $shipping->getCountryId(),
                'phoneNumber' => $shipping->getTelephone()
            )
        );

        // Save payment method
        if ( $this->checkoutSession->getData( \Mony\Mony\Controller\Payment\Process::ADDITIONAL_INFORMATION_KEY_MONY_SAVE_CARD ) ) {
            $data['save'] = true;
        }

        return $data;
    }

    /**
     * Get customer info for mony to create order
     *
     * @param $payment
     * @param null $monyCustomerId
     * @return array
     */
    public function getCustomerInfo($payment, $monyCustomerId = null)
    {
        $order = $payment->getOrder();
        // if Mony Customer Id provided, return only customer id
        if ($monyCustomerId) {
             $data = array(
                'email' => $order->getCustomerEmail(),
                'customerId' => $monyCustomerId,
            );
            return $data;
        } else { // return email if not login
            return array(
                'email' => $order->getCustomerEmail()
            );
        }
    }

    /**
     * Get merchant reference for API
     *
     * @param $order
     * @return mixed
     */
    public function getMerchantReference($order)
    {
        return $order->getIncrementId();
    }

    /**
     * Get Order details for API
     *
     * @param $order
     * @return mixed
     */
    public function getOrderDetail($order)
    {
        $data['items'] = $this->_itemData($order->getAllVisibleItems(), $order->getOrderCurrencyCode());

        if ($shipping = $order->getShippingAddress()) {
            $data['shippingAddress'] = array(
                'name' => $shipping->getName(),
                'address1' => array_values($shipping->getStreet())[0],
                'address2' => array_key_exists(1, $shipping->getStreet()) ? array_values($shipping->getStreet())[1] : '',
                'suburb'   => $shipping->getCity(),
                'state'    => $shipping->getRegion(),
                'postcode' => $shipping->getPostcode(),
                'countryCode' => $shipping->getCountryId(),
                'phoneNumber' => $shipping->getTelephone(),
            );
        }
        return $data;
    }

    /**
     * Get Item Data needed for Mony API
     *
     * @param $items
     * @param string $currency
     *
     * @return array
     */
    protected function _itemData($items, $currency = 'AUD')
    {
        // set original data
        $data = array();

        // looping all item data that needed for API
        foreach ($items as $item)
        {
            $data[] = array(
                'name' => $item->getName(),
                'sku'  => $item->getSku(),
                'quantity'  => $item->getQtyOrdered(),
                'price' => array(
                    'amount' => $item->getPrice(),
                    'currency' => $currency,
                )
            );

        }
        // return the item data
        return $data;
    }
    
    /**
     * Get configured gateway URL for payment method
     *
     * @return string
     */
    public function getRefundData($amount, $currency = 'AUD')
    {
        $refund_data =  array(
                            "amount"    =>  array(
                                                "amount"        =>  $amount,
                                                "currency"      =>  $currency,
                                            )
                        );
        return $refund_data;
    }


    /*-----------------------------------------------------------------------------------------
        To be Replaced with routers
    -----------------------------------------------------------------------------------------*/

    /**
     * Get configured gateway URL for payment method
     *
     * @return string|null
     */
    public function getOrdersApiUrl()
    {
        return $this->monyConfig->getApiUrl('orders/');
    }

    /**
     * Get configured gateway URL for payment method
     *
     * @return string|null
     */
    public function getCustomersApiUrl($query = null, $type = false)
    {
        $url = $this->monyConfig->getApiUrl('customers');

        /**
         * Type of customer REST URL
         */
        switch ($type) {
            case self::CUSTOMER_METHOD_GET:
                $url .= '/' . $query['id'];
                break;
            case self::CUSTOMER_METHOD_SEARCH:
                $i = 0;
                foreach ($query as $attribute => $value) {
                    if ($i < 1) {
                        $url .= '?';
                    } else {
                        $url .= '&';
                    }
                    $url .= $attribute . '=' . $value;
                };
                break;
            case self::CUSTOMER_METHOD_DELETE:
                $url .= '/' . $query['id'] . '/payment-method/' . $query['payment-method'];
                break;
            default:
                break;

        }

        return $url;
    }
    
    /**
     * Get configured gateway URL for payment method
     *
     * @return string
     */
    public function getRefundUrl($id)
    {
        return $this->monyConfig->getApiUrl('orders/' . $id . "/refunds");
    }
}