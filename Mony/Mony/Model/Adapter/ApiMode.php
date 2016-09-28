<?php
/**
 * Magento 2 extensions for Mony Payment
 *
 * @author Mony <steven.gunarso@touchcorp.com>
 * @copyright 2016 Mony https://www.monypayments.com.au/
 */
namespace Mony\Mony\Model\Adapter;

class ApiMode
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    protected $environments;

    /**
     * Mode constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param array $environments
     */
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, $environments = [])
    {
        $this->scopeConfig = $scopeConfig;
        $this->environments = $environments;
    }

    /**
     * Get All API modes from di.xml
     *
     * @return array
     */
    public function getAllApiModes()
    {
        return $this->environments;
    }

    /**
     * Get current API mode based on configuration
     *
     * @return array
     */
    public function getCurrentMode()
    {
        return $this->environments[$this->scopeConfig->getValue('payment/' . \Mony\Mony\Model\Payment::CODE . '/api_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)];
    }
}