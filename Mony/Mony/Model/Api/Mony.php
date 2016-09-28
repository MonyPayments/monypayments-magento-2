<?php
/**
 * @package   Mony_Mony
 * @author    Mony payments <support@monypayments.com>
 * @copyright Copyright (c) 2015-2016 Mony payments (http://www.monypayments.com)
 */
namespace Mony\Mony\Model\Api;

class Mony
{
    /**
     * @var string
     */
    protected $_userAgent;

    /**
     * Get useragent on construct and set as variable
     *
     * Mony_Mony_Model_Api_Mony constructor.
     */
    public function __construct()
    {

        // $this->_userAgent = 'MonypaymentsMagentoPlugin/' . $this->_helper()->getModuleVersion() . ' (Magento ' . Mage::getEdition() . ' ' . Mage::getVersion() . ')';
        return $this;
    }

    /**
     * @return Mony_Mony_Helper_Data
     */
    protected function _helper()
    {
        // return Mage::helper('monypayments');
    }
}