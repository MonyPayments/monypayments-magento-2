<?php
/**
 * Magento 2 extensions for Mony Payment
 *
 * @author Mony <steven.gunarso@touchcorp.com>
 * @copyright 2016 Mony https://www.monypayments.com.au/
 */
namespace Mony\Mony\Model\Cron;

use \Mony\Mony\Model\Config\Mony as MonyConfig;
use \Mony\Mony\Model\Adapter\Request\Call as MonyApiCall;
use \Mony\Mony\Helper\Data as Helper;

/**
 * Class Cron
 * @package Mony\Mony\Model
 */
class Order
{
    /**
     * Constant variable
     */
    const ORDERS_PROCESSING_LIMIT = 50;

    /**
     * @var |OrderFactory|Response|JsonData|
     */
    protected $orderFactory;

    protected $helper;

    protected $date;
    protected $timezone;

    protected $invoiceService;
    protected $transactionFactory;

    protected $salesOrderConfig;

    protected $monyConfig;
    protected $monyApiCall;

    /**
     * Order constructor.
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Magento\Sales\Model\Order\Config $salesOrderConfig
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param MonyConfig $monyConfig
     * @param MonyCall $monyCall
     * @param MonyCall $monyHelper
     */
    public function __construct(
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Config $salesOrderConfig,
        MonyConfig $monyConfig,
        MonyApiCall $monyApiCall,
        Helper $monyHelper
    ) {
        $this->orderFactory = $orderFactory;

        $this->helper = $monyHelper;

        $this->jsonHelper = $jsonHelper;
        $this->date = $date;
        $this->timezone = $timezone;

        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;

        $this->salesOrderConfig = $salesOrderConfig;

        $this->monyConfig = $monyConfig;
        $this->monyApiCall = $monyApiCall;
    }

    /**
     * crontab function to get payment update
     */
    public function execute()
    {
        try {

            // adding current magento scope datetime with 30 mins calculations
            $requestDate = $this->date->gmtDate( null, $this->timezone->scopeTimeStamp() );

            /**
             * Load the order along with payment method and additional info
             */
            $orderCollection = $this->orderFactory->create()->getCollection();

            // join with payment table
            $orderCollection->getSelect()
                ->join(
                    array('payment' => 'sales_order_payment'),
                    'main_table.entity_id = payment.parent_id',
                    array('method', 'additional_information', 'last_trans_id')
                );

            // add filter for state and payment method
            $orderCollection->addFieldToFilter('main_table.state', array('eq' => \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW))
                ->addFieldToFilter('payment.method', array('eq' => \Mony\Mony\Model\Payment::CODE))
                ->addFieldToFilter('main_table.created_at', array('lt' => $requestDate));

            $orderCollection->setPageSize(50); //load the first 50 order first

            // set start cron
            $this->helper->debug('Cron Process Running');

            /**
             * Looping the order and processing each one
             */
            foreach ($orderCollection as $order) {
                $this->processPaymentReview($order);
            }

            $this->helper->debug('Cron Process Finished');
        }
        catch (Exception $e) {

            $this->helper->debug('Cron Process Error: ' . $e->getMessage());
        }
    }

    /**
     * Process each individual payment review orders
     */
    public function processPaymentReview($order) {
        // load payment
        $payment = $order->getPayment();

        // check if token is exist
        if ($mony_id = $payment->getAdditionalInformation(\Mony\Mony\Model\Config\Mony::MONY_TRANSACTION_ID)) {
            $response = $this->getPaymentById($mony_id);   // set start cron

            // check the result of API
            switch ($response['status']) {
                case \Mony\Mony\Model\Payment::RESPONSE_STATUS_APPROVED:
                    // Adding order ID to payment, create invoice and processing the order
                    $this->updatePayment($order, $response['id']);
                    $this->createInvoiceAndUpdateOrder($order, $response['id']);
                    $payment->setIsTransactionApproved(true);
                    break;
                case \Mony\Mony\Model\Payment::RESPONSE_STATUS_DECLINED;
                    // cancel the order if found order declined
                    $this->cancelOrder($order, __('Payment declined by Mony'));
                    break;
                case \Mony\Mony\Model\Payment::RESPONSE_STATUS_FAILED;
                    // cancel the order if found order declined
                    $this->cancelOrder($order, __('Payment Failed'));
                    break;
            }
        } 
        else {
            // cancel order if token is not found
            $this->cancelOrder($order, __('Payment Not Found'));
        }
    }


    //------------------------------------------------------------------------------------------------
    // API Call Section
    //------------------------------------------------------------------------------------------------
    public function getPaymentById( $mony_id ) {
        $target_url = $this->monyConfig->getApiUrl('orders/' . $mony_id );
            
        // call API to capture the payment
        $response = $this->monyApiCall->send(
            $target_url,
            array(),
            \Magento\Framework\HTTP\ZendClient::GET
        );

        return $response;
    }


    //------------------------------------------------------------------------------------------------
    // Approval Section
    //------------------------------------------------------------------------------------------------

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param $orderId
     */
    public function updatePayment(\Magento\Sales\Model\Order $order, $orderId)
    {
        // adding Mony order id to the payment
        $payment = $order->getPayment();
        $payment->setTransactionId($orderId);
        $payment->setAdditionalInformation(\Mony\Mony\Model\Config\Mony::MONY_TRANSACTION_ID, $orderId);
        $payment->save(); // have save here to link mony order id right after checking the API

        // debug mode
        $this->helper->debug('Added Mony Payment ID ' . $orderId . ' for Magento order ' . $order->getIncrementId());
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param $orderId
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws bool
     */
    public function createInvoiceAndUpdateOrder(\Magento\Sales\Model\Order $order, $orderId)
    {
        /**
         * Set the state of order to be processing, run in transaction along with creating invoice
         * Making sure the order won't change to processing if invoice not created.
         *
         * So then, cron will handle this gracefully.
         */
        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
            ->setStatus($this->salesOrderConfig->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING));

        $order->addStatusHistoryComment(__('Payment approved by Mony'));

        // prepare invoice and generate it
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE); // set to be capture offline because the capture has been done previously
        $invoice->register();

        /** @var \Magento\Framework\DB\Transaction $transaction */
        $transaction = $this->transactionFactory->create();
        $transaction->addObject($order)
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();

        // debug mode
        $this->helper->debug('Invoice created and update status for Magento order ' . $order->getIncrementId());
    }


    //------------------------------------------------------------------------------------------------
    // Cancellation Section
    //------------------------------------------------------------------------------------------------
    
    /**
     * @param \Magento\Sales\Model\Order $order
     * @param bool $comment
     * @return $this
     */
    public function cancelOrder(\Magento\Sales\Model\Order $order, $comment = false)
    {
        if (!$order->isCanceled() &&
            $order->getState() !== \Magento\Sales\Model\Order::STATE_COMPLETE &&
            $order->getState() !== \Magento\Sales\Model\Order::STATE_CLOSED) {

            // perform this before order process or cancel
            // $this->_beforeUpdateOrder($order);

            // perform adding comment
            if ($comment) {
                $order->addStatusHistoryComment($comment);
            }
            
            $payment = $order->getPayment();
            $payment->setIsTransactionDenied(true);

            //not sure this is working or not
            $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED)
                ->setStatus("canceled");

            $order->cancel();
            $order->save();

            // debug mode
            $this->helper->debug('Cancel order for Magento order ' . $order->getIncrementId());
        }
        return $this;
    }

    /**
     * On processing or canceling the order, payment_review cannot be changed.
     * Perform this task first before processing or canceling the order
     *
     * @param $order
     * @return $this
     */
    protected function _beforeUpdateOrder($order)
    {
        // change the order status if payment review
        if ($order->isPaymentReview()) {
            $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
                ->setStatus('pending_payment');
        }
        return $this;
    }

}