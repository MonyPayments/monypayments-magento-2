<?php
/**
 * Magento 2 extensions for Mony Payment
 *
 * @author Mony <steven.gunarso@touchcorp.com>
 * @copyright 2016 Mony https://www.monypayments.com.au/
 */
namespace Mony\Mony\Model\Adapter;

class CardTypes
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    protected $cctypes;

    /**
     * Mode constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param array $environments
     */
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, $cctypes = [])
    {
        $this->scopeConfig = $scopeConfig;
        $this->cctypes = $cctypes;
    }

    /**
     * Get All API modes from di.xml
     *
     * @return array
     */
    public function getAllCardTypes()
    {
        return $this->cctypes;
    }

    /**
     * Get current API mode based on configuration
     *
     * @return array
     */
    public function getCurrentType()
    {
        return $this->cctypes[$this->scopeConfig->getValue('payment/' . \Mony\Mony\Model\Payment::CODE . '/cctypes', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)];
    }
}