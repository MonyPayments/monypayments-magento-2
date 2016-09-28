<?php
namespace Mony\Mony\Model;

use \Mony\Mony\Helper\Data as Helper;
use \Mony\Mony\Model\Api\Adapter as Adapter;
use \Magento\Framework\Exception\LocalizedException as LocalizedException;
use \Magento\Checkout\Model\Session as CheckoutSession;
use \Mony\Mony\Model\Config\Mony as MonyConfig;
use \Mony\Mony\Model\Adapter\Request\Call as MonyCall;
use \Mony\Mony\Model\Cron\Order as MonyOrder;
use \Magento\Store\Model\StoreManagerInterface as StoreManager;
use \Mony\Mony\Model\Data\Customer as Customer;

 
class Payment extends \Magento\Payment\Model\Method\Cc {
    const CODE = 'mony_mony';
 
    protected $_code = self::CODE;
 
    /* Configuration fields */
    const API_MODE_CONFIG_FIELD = 'api_mode';

    // const API_URL_CONFIG_PATH_PATTERN = 'monypayments/api/{prefix}_api_url';
    // const WEB_URL_CONFIG_PATH_PATTERN = 'monypayments/api/{prefix}_web_url';
    // const PAYMENT_SCRIPT_PATH_PATTERN = 'monypayments/api/{prefix}_payment_script_path';
    // const RESOURCE_BASE               = 'monypayments/api/resource_base';

    /* Order payment statuses */
    const RESPONSE_STATUS_APPROVED = 'APPROVED';
    const RESPONSE_STATUS_PENDING  = 'PENDING';
    const RESPONSE_STATUS_FAILED   = 'FAILED';
    const RESPONSE_STATUS_DECLINED = 'DECLINED';


    const CARD_DECLINED_MESSAGE = "CARD_DECLINED";

    /**
     * Payment Method features common for all payment methods
     *
     * @var bool
     */
    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canUseInternal              = false;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = false;
    protected $_canReviewPayment            = true;
    protected $_canFetchTransactionInfo     = true;
    protected $_canSaveCc                   = false;
    protected $_canManageRecurringProfiles  = false;
 
    protected $_countryFactory;
 
    protected $_supportedCurrencyCodes = array('AUD');
 
    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];

    /**
     * Custom protected variable for Mony
     */
    protected $_transactionData;

    /**
     * @var MonyHelper $helper
     */
    protected $helper;
    /**
     * @var MonyAdapter $adapter
     */
    protected $adapter;
    /**
     * @var CheckoutSession $checkoutSession
     */
    protected $checkoutSession;
    /**
     * @var MonyConfig $monyConfig
     */
    protected $monyConfig;

    /**
     * @var MonyConfig $monyApiCall
     */
    protected $monyApiCall;

    /**
     * @var MonyOrder $monyOrder
     */
    protected $monyOrder;

    /**
     * @var StoreManager $storeManager
     */
    protected $storeManager;

    /**
     * @var Customer $monyCustomer
     */
    protected $monyCustomer;

    //this is to override the Admin Order - Payment Info
    protected $_infoBlockType = 'Mony\Mony\Block\Info';

 
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        Helper $monyHelper,
        Adapter $monyAdapter,
        CheckoutSession $checkoutSession,
        MonyConfig $monyConfig,
        MonyCall $monyApiCall,
        MonyOrder $monyOrder,
        StoreManager $storeManager,
        Customer $monyCustomer,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );
 
        $this->_countryFactory = $countryFactory;
        $this->helper = $monyHelper;
        $this->adapter = $monyAdapter;
        $this->checkoutSession = $checkoutSession;
        $this->monyConfig = $monyConfig;
        $this->monyApiCall = $monyApiCall;
        $this->monyOrder = $monyOrder;

        $this->storeManager = $storeManager;

        $this->monyCustomer = $monyCustomer;
    }
    /**
     * Authorize payment
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @param float $amount
     * @return $this
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::authorize();
        $this->_authorize($payment, $amount, false);
        return $this;
    }

    /**
     * Authorize function to prepare data and check on available data
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     *
     * @return $this
     *
     * @throws Mage_Payment_Model_Info_Exception
     */
    protected function _authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $mony_payment_token = $this->checkoutSession->getData( \Mony\Mony\Controller\Payment\Process::ADDITIONAL_INFORMATION_KEY_MONY_TOKEN );
        // Check if Token is empty
        // if (!$payment->getMonypaymentsToken()) {
        if (!$mony_payment_token) {
            $this->helper->debug('Capture Error: Mony Cart Token cannot be empty');

            $orderDetail = $this->adapter->getOrderDetail($payment->getOrder());

            // throw new LocalizedException( __(json_encode($orderDetail)) );
            throw new LocalizedException( __("There was an error capturing the transaction.") );
        }

        try {

            $order = $payment->getOrder();
            // Check on customer and created if not exist and saved
            $monyCustomerId = false;
            if ($order->getCustomerId()) {
                $monyCustomerId = $this->monyCustomer->registerCustomer($payment);
            }

            // preparing data through Adapter API
            $order = $payment->getOrder();

            // Charge amount
            $chargeAmount = $this->adapter->getChargeAmount($order, $amount);
            if ($chargeAmount) {
                $this->_transactionData['chargeAmount'] = $chargeAmount;
            }

            // Payment Methods
            $paymentMethod = $this->adapter->getPaymentMethod($payment);
            if ($paymentMethod) {
                $this->_transactionData['paymentMethod'] = $paymentMethod;
            }

            // Customer details
            $details = $this->adapter->getCustomerInfo($payment, $monyCustomerId);
            foreach ($details as $type => $info) {
                $this->_transactionData[$type] = $info;
            }

            // Merchant Reference
            $mechantReference = $this->adapter->getMerchantReference($order);
            if ($mechantReference) {
                $this->_transactionData['merchantReference'] = $mechantReference;
            }

            // Order Details
            $orderDetail = $this->adapter->getOrderDetail($order);
            if ($orderDetail) {
                $this->_transactionData['orderDetail'] = $orderDetail;
            }

            // Add Request to logs if debug mode on
            $this->helper->debug( 'Order Request: ' . json_encode($this->_transactionData) );

        } catch (Exception $e) {
            // Add Request to logs if debug mode on
            $this->helper->debug('Order Request Error: ' . $e->getMessage());

            // Throw error to Magento Checkout
            throw new LocalizedException( __($e->getMessage()) );
        }
        return $this;
    }


    /**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        // Do Authorize
        if (!$this->_transactionData) {
            $this->_authorize($payment, $amount);
        }

        // Actual capture the payment to API
        try {
            // call API to capture the payment
            $response = $this->monyApiCall->send(
                $this->adapter->getOrdersApiUrl(),
                $this->_transactionData,
                \Magento\Framework\HTTP\ZendClient::POST
            );

            // Add response to the log if debug mode on
            $this->helper->debug( json_encode( array('Order Response' => $response) ) );

            if (isset($response['status'])) {
                switch ($response['status']) {
                    case self::RESPONSE_STATUS_APPROVED:
                        $payment->setTransactionId($response['id'])
                            ->setAdditionalInformation(\Mony\Mony\Model\Config\Mony::MONY_TRANSACTION_ID, $response['id']);
                        break;
                    case self::RESPONSE_STATUS_PENDING:
                        $payment->setTransactionId($response['id'])
                            ->setAdditionalInformation(\Mony\Mony\Model\Config\Mony::MONY_TRANSACTION_ID, $response['id'])
                            ->setIsTransactionPending(true);
                        break;
                    default: // Any response that is not approved and pending
                        throw new LocalizedException( __($response['statusReason']) );
                        break;
                }
            } else { // Any response that doesn't have status on response
                throw new LocalizedException( __('Unable to get the status response from API') );
            }
        } catch (Exception $e) {
            // Add response to the log if debug mode on
            $this->helper->debug('Order Response Error: ' . $e->getMessage());

            if( $e->getMessage() == self::CARD_DECLINED_MESSAGE ) {
                // Throw error to Magento Checkout
                throw new LocalizedException( __('Transaction has failed. Please use another card.') );
            }

            // Throw error to Magento Checkout
            throw new LocalizedException( __('There was an error capturing the transaction. Please try again.') );
        }
        return $this;
    }

    /**
     * Payment refund
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $mony_transaction_id = $payment->getAdditionalInformation(\Mony\Mony\Model\Config\Mony::MONY_TRANSACTION_ID);

        $refund_data = $this->adapter->getRefundData($amount);

        try {
            // call API to capture the payment
            $response = $this->monyApiCall->send(
                $this->adapter->getRefundUrl( $mony_transaction_id ),
                $refund_data,
                \Magento\Framework\HTTP\ZendClient::POST
            );

            // Add response to the log if debug mode on
            $this->helper->debug( json_encode( array('Refund Response' => $response) ) );

            if ( !empty($response["errorId"]) ) {

                // Show an error message in Magento Admin
                throw new LocalizedException( __('There was an error refunding the transaction.' . $response["message"] ) );
            } 
            else {
                // Set data on Additional Information for later use on CreditMemo creation
                $payment->setAdditionalInformation(
                    array(
                        'refund_id' => $response["id"],
                        'refund_date' => $response["createdDate"],
                        'refund_amount' => $response["amount"]["amount"],
                    )
                );
            }

        } 
        catch (Exception $e) {

            // Show an error message in Magento Admin
            throw new LocalizedException( __('There was an error refunding the transaction.' . $response["message"] ) );
        }

        return $this;
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$this->getConfigData('active')) {
            return false;
        }
        else if (!$this->getConfigData('merchant_secret')) {
            return false;
        }
        else if (!$this->getConfigData('merchant_id')) {
            return false;
        }
        else if (!$this->getConfigData('api_key')) {
            return false;
        }

        return true;
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            //return false;
        }
        return true;
    }

    /*-------------------------------------------------------------------------------------------------------
                                Fetch Transaction Info (Get Update Payment) in Admin
    -------------------------------------------------------------------------------------------------------*/
    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $transactionId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function fetchTransactionInfo(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {
        // Debug mode
        $this->helper->debug('Start \Mony\Mony\Model\Payment::fetchTransactionInfo()');

        $order = $payment->getOrder();

        $this->monyOrder->processPaymentReview($order);

        // Debug mode
        $this->helper->debug('Finished \Mony\Mony\Model\Payment::fetchTransactionInfo()');

        // return to the parent
        return parent::fetchTransactionInfo($payment, $transactionId);
    }




    /*-------------------------------------------------------------------------------------------------------
                                OVERRIDDEN FUNCTION FROM CORE
    -------------------------------------------------------------------------------------------------------*/

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function validate()
    {
        //Disable the default validation because it has been done through Mony anyway
        return $this;
    }
}