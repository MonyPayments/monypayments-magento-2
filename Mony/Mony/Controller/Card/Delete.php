<?php
/**
 * Magento 2 extensions for Mony Payment
 *
 * @author Mony <steven.gunarso@touchcorp.com>
 * @copyright 2016 Mony https://www.monypayments.com.au/
 */
namespace Mony\Mony\Controller\Card;

use \Magento\Framework\View\Result\PageFactory as PageFactory;
use \Mony\Mony\Model\Data\Customer as Customer;
use \Magento\Customer\Model\Session as Session;


/**
 * Class Response
 * @package Mony\Mony\Controller\Card
 */
class Delete extends \Magento\Framework\App\Action\Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Customer $monyCustomer
     */
    protected $monyCustomer;

    /**
     * @var Session $customerSession
     */
    protected $customerSession;


    /**
     * Response constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        PageFactory $resultPageFactory,
        Customer $monyCustomer,
        Session $customerSession
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->monyCustomer = $monyCustomer;
        $this->customerSession = $customerSession;
    }


    public function execute() {

        $resultPage = $this->resultPageFactory->create();
        
        $customer = $this->monyCustomer->getCustomerFromSession();

        if( empty($customer) ) {
            //force a redirect to login page
            $this->customerSession->authenticate();
        }
        else {

            if ($token = $this->getRequest()->getParam('token')) {

                $response = $this->monyCustomer->deleteSavedCard($token, NULL);

                // // Handling error and success message
                if ($response) {
                    $this->messageManager->addSuccess(__('Card has been successfully deleted'));
                } else {
                    $this->messageManager->addError(__('There was an error deleting your payment. Please contact the administrator.'));
                }
            }

            // // redirect to previous page
            $this->_redirect('mony/card/saved');
        }

        return $resultPage;
    }
}