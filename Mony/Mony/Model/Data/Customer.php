<?php
/**
 * Magento 2 extensions for Mony Payment
 *
 * @author Mony <steven.gunarso@touchcorp.com>
 * @copyright 2016 Mony https://www.monypayments.com.au/
 */
namespace Mony\Mony\Model\Data;

use \Magento\Customer\Model\Session as Session;
use \Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use \Mony\Mony\Helper\Data as Helper;
use \Mony\Mony\Model\Api\Adapter as Adapter;
use \Mony\Mony\Model\Config\Mony as MonyConfig;
use \Mony\Mony\Model\Adapter\Request\Call as MonyCall;
use \Magento\Framework\Exception\LocalizedException as LocalizedException;

/**
 * Class ApiMode
 * @package Mony\Mony\Model\Source
 */
class Customer
{
    /**
     * @var MonyHelper $helper
     */
    protected $helper;
    
    /**
     * @var MonyAdapter $adapter
     */
    protected $adapter;

    /**
     * @var MonyConfig $monyConfig
     */
    protected $monyConfig;

    /**
     * @var MonyConfig $monyApiCall
     */
    protected $monyApiCall;

    /**
     * @var Session $customerSession
     */
    protected $customerSession;

    /**
     * @var CustomerRepository $customerRepository
     */
    protected $customerRepository;


    /**
     * Response constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Helper $helper,
        Adapter $adapter,
        MonyConfig $monyConfig,
        MonyCall $monyApiCall,
        Session $customerSession,
        CustomerRepository $customerRepository
    ) {

        $this->helper = $helper;
        $this->adapter = $adapter;
        $this->monyConfig = $monyConfig;
        $this->monyApiCall = $monyApiCall;
        $this->customerSession = $customerSession;

        $this->customerRepository = $customerRepository;
    }

    public function getCustomerFromSession() {

        if( $this->customerSession->isLoggedIn() ) {
            $customer = $this->customerSession->getCustomer();
            return $customer;
        }

        return null;

    }

    /*---------------------------------------------------------------------------------------
                    Mony Operational Functionality from M1 - Cards Ops
    ---------------------------------------------------------------------------------------*/
    /**
     * Get payment methods saved cards based on customer
     *
     * @param $customer
     * @return bool | array
     */
    public function getSavedCards($customer = NULL)
    {   
        // if not defined, try to get customer from session 
        if( !empty($customer) ) {
            $customer = $this->customerRepository->getById( $customer->getId() );
        }
        else if( !empty($this->customerSession->getCustomer() ) ) {
            $customer = $this->customerRepository->getById( $this->customerSession->getCustomer()->getId() );
        }
        else {
            return NULL;
        }

        $method = \Mony\Mony\Model\Api\Adapter::CUSTOMER_METHOD_GET;

        if( !empty($customer->getCustomAttribute('mony_customer_id')) ) {
            $query =    array(
                            'id' => $customer->getCustomAttribute('mony_customer_id')->getValue()
                        );


            // Customer find and found on API
            if ($savedCards = $this->findCustomer($query, $method )) {

                return $savedCards;
            }
        }

        return NULL;
    }
    /**
     * Delete payment methods saved cards based on Card Token and Mony ID 
     *
     * @param $customer
     * @param $token
     * @return bool | array
     */
    public function deleteSavedCard($token = NULL, $customer = NULL)
    {   
        // if not defined, try to get customer from session 
        if( empty($customer) ) {
            $customer = $this->customerRepository->getById( $this->customerSession->getCustomer()->getId() );
        }
        else {
            $customer = $this->customerRepository->getById( $customer->getId() );
        }

        $method = \Mony\Mony\Model\Api\Adapter::CUSTOMER_METHOD_DELETE;

        if( !empty($customer->getCustomAttribute('mony_customer_id')) ) {
            $query =    array(
                            'id' => $customer->getCustomAttribute('mony_customer_id')->getValue(),
                            'payment-method' => $token,
                        );
            
            $url = $this->adapter->getCustomersApiUrl($query, $method);

            // Adding to log
            $this->helper->debug( json_encode( array('Customer Card Delete Request' => $url) ) );

            // call API to find the customer
            $response = $this->monyApiCall->send(
                $url,
                false,
                \Magento\Framework\HTTP\ZendClient::DELETE
            );

            // Adding to log
            $this->helper->debug( json_encode( array('Customer Card Delete Response' => $response) ) );

            if (!empty($response["statusCode"]) && $response["statusCode"] == \Mony\Mony\Model\Api\Adapter::CUSTOMER_DELETE_STATUS_OK ) {
                return true;
            }
        }

        return false;
    }

    /*---------------------------------------------------------------------------------------
                            Mony Operational Functionality from M1
    ---------------------------------------------------------------------------------------*/
    /**
     * Register customer
     *
     * @param Varien_Object $payment
     * @return mixed
     */
    public function registerCustomer(\Magento\Sales\Model\Order\Payment\Interceptor $payment)
    {
        $monyCustomerId = null;
        $order = $payment->getOrder();

        $customer = $this->customerRepository->getById( $order->getCustomerId() );

        // If customer not linked to Mony Customer and want save
        if ( empty($customer->getCustomAttribute('mony_customer_id')) || 
                empty($customer->getCustomAttribute('mony_customer_id')->getValue()) || 
                strlen(trim($customer->getCustomAttribute('mony_customer_id')->getValue())) < 1 ) {

            // Customer find and found on API
            if ($customerFound = $this->findCustomer(array('email' => $customer->getEmail()))) {
                $monyCustomer = array_shift($customerFound);
                $monyCustomerId = $monyCustomer['id'];
            }
            // Customer not found on API
            else {
                $customerData['email'] = $customer->getEmail();

                /**
                 * run to get optionals data
                 */
                // Firstname
                if ($firstname =  $customer->getFirstname()) {
                    $customerData['givenNames'] = $firstname;
                }

                // Lastname
                if ($lastname = $customer->getLastname()) {
                    $customerData['surname'] = $lastname;
                }

                // Telephone
                if ($phoneNumber = $order->getShippingAddress()->getTelephone()) {
                    $customerData['phoneNumber'] = $phoneNumber;
                }

                // Adding lo log
                $this->helper->debug( json_encode( array('Customer Register Request' => $customerData) ) );

                // Create customer in Mony API
                $response = $this->monyApiCall->send(
                    $this->adapter->getCustomersApiUrl(),
                    $customerData,
                    \Magento\Framework\HTTP\ZendClient::POST
                );

                // Adding lo log
                $this->helper->debug( json_encode( array('Customer Register Response' => json_encode($response) ) ) );

                // If success
                if (!empty($response['id'])) {
                    $monyCustomerId = $response['id'];
                } else { // Not success
                    throw new LocalizedException( __("There was an issue when creating customer on Mony payments.") );
                }
            }

            /**
             * Linked Magento customer to Mony payments ID
             *
             * NOTE: Even it happen saving customer in Magento, this is belong to one transaction with Order.
             * So the customer will successfully updated when order is successful
             */
            $customer->setCustomAttribute('mony_customer_id', $monyCustomerId);
            $customer = $this->customerRepository->save($customer);
        }
        else {
            $monyCustomerId = $customer->getCustomAttribute('mony_customer_id')->getValue();
        }

        // Return with Mony customer ID
        return $monyCustomerId;
    }

    /**
     * Find customer through API
     *
     * @param array $search
     * @return bool | array
     */
    public function findCustomer(array $search, $method = \Mony\Mony\Model\Api\Adapter::CUSTOMER_METHOD_SEARCH)
    {
        // Search the Customer URL
        $url = $this->adapter->getCustomersApiUrl($search, $method);

        // Adding to log
        $this->helper->debug( json_encode( array('Customer Search Request' => $url) ) );
            
        // call API to find the customer
        $response = $this->monyApiCall->send(
            $url,
            array(),
            \Magento\Framework\HTTP\ZendClient::GET
        );

        // Adding to log
        $this->helper->debug( json_encode( array('Customer Search Response' => $response) ) );


        // If error occur
        if (empty($response['results']) && empty($response['paymentMethods']) ) {
            return false;
        }
        else if( !empty($response['results']) ) {
            // return array from response
            return $response['results'];
        }
        else if( !empty($response['paymentMethods']) ) {
            // return array from response
            return $response['paymentMethods'];
        }
        else {
            return false;
        }
    }

}