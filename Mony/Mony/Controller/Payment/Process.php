<?php
/**
 * Magento 2 extensions for Mony Payment
 *
 * @author Mony <steven.gunarso@touchcorp.com>
 * @copyright 2016 Mony https://www.monypayments.com.au/
 */
namespace Mony\Mony\Controller\Payment;

use \Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Class Response
 * @package Mony\Mony\Controller\Payment
 */
class Process extends \Magento\Framework\App\Action\Action
{
    protected $checkoutSession;
    const ADDITIONAL_INFORMATION_KEY_MONY_TOKEN     =   'mony_payment_token';
    const ADDITIONAL_INFORMATION_KEY_MONY_DIGIT     =   'mony_payment_last4';
    const ADDITIONAL_INFORMATION_KEY_MONY_CC_ENC    =   'mony_payment_cc_number_enc';
    const ADDITIONAL_INFORMATION_KEY_MONY_SAVE_CARD =   'mony_payment_cc_save_card';

    /**
     * Response constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
    }


    public function execute() {
        $query = $this->getRequest()->getParams();
        $order = $this->checkoutSession->getLastRealOrder();

        $this->_clearSession();

        if( !empty($query["monypayments_token"]) ) {
            $this->checkoutSession->setData( self::ADDITIONAL_INFORMATION_KEY_MONY_TOKEN, $query["monypayments_token"] );
        }

        if( !empty($query["cc_last4"]) ) {
            $this->checkoutSession->setData( self::ADDITIONAL_INFORMATION_KEY_MONY_DIGIT, $query["cc_last4"] );
        }

        if( !empty($query["cc_number_enc"]) ) {
            $this->checkoutSession->setData( self::ADDITIONAL_INFORMATION_KEY_MONY_CC_ENC, $query["cc_number_enc"] );
        }

        if( !empty($query["cc_save_card"]) ) {
            $this->checkoutSession->setData( self::ADDITIONAL_INFORMATION_KEY_MONY_SAVE_CARD, $query["cc_save_card"] );
        }

        die( json_encode( array("success" => true) ) );
    }   

    private function _clearSession() {
        //clear session, in case the users made multiple transaction
        //getData and then remove (2nd variable)
        $this->checkoutSession->getData( self::ADDITIONAL_INFORMATION_KEY_MONY_TOKEN, true );
        $this->checkoutSession->getData( self::ADDITIONAL_INFORMATION_KEY_MONY_DIGIT, true );
        $this->checkoutSession->getData( self::ADDITIONAL_INFORMATION_KEY_MONY_CC_ENC, true );
        $this->checkoutSession->getData( self::ADDITIONAL_INFORMATION_KEY_MONY_SAVE_CARD, true );
    }
}