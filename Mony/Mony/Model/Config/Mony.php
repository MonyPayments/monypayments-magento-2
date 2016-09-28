<?php
/**
 * Magento 2 extensions for Mony Payment
 *
 * @author Mony <steven.gunarso@touchcorp.com>
 * @copyright 2016 Mony https://www.afterpay.com.au/
 */
namespace Mony\Mony\Model\Config;

use Mony\Mony\Model\Adapter\ApiMode;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfigInterface;

/**
 * Class Mony
 * @package Mony\Mony\Model\Config
 */
class Mony
{
    /**
     * constant data for static
     */
    const ACTIVE                    = 'active';
    const API_URL_XML_NODE          = 'api_url';
    const WEB_URL_XML_NODE          = 'web_url';
    const CHECKOUT_MODE_XML_NODE    = 'payment_display';
    const MERCHANT_ID_XML_NODE      = 'merchant_id';
    const MERCHANT_SECRET_XML_NODE  = 'merchant_secret';
    const API_KEY_XML_NODE          = 'api_key';
    const PAYMENT_ACTION            = 'payment_action';
    const DEBUG_MODE                = 'debug';
    const SAVE_CARDS_MODE           = 'save_cards';

    //Used in the Admin & Transaction saving
    const MONY_TRANSACTION_ID       = 'mony_transaction_id';

    /**
     * @var ApiMode
     */
    protected $apiMode;

    protected $scopeConfig;

    protected $storeId = null;

    /**
     * Payovertime constructor.
     * @param ApiMode $apiMode
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ApiMode $apiMode,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->apiMode = $apiMode;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get API Url based on configuration
     *
     * @param string $path
     * @param array $query
     * @return bool|string
     */
    public function getApiUrl($path = '', $query = [])
    {
        return $this->_getRequestedUrl(self::API_URL_XML_NODE, $path, $query);
    }

    /**
     * Get Web API url based on configuration
     *
     * @param string $path
     * @param array $query
     * @return bool|string
     */
    public function getWebUrl($path = '', $query = [])
    {
        return $this->_getRequestedUrl(self::WEB_URL_XML_NODE, $path, $query);
    }

    /**
     * Calculated the url to generate api/web url
     *
     * @param $type
     * @param $path
     * @param $query
     * @return bool|string
     */
    protected function _getRequestedUrl($type, $path, $query)
    {
        $currentApi = $this->apiMode->getCurrentMode();
        if (array_key_exists($type, $currentApi)) {
            // set the url and path
            $url = $currentApi[$type] . $path;

            // calculate the query
            if (!empty($query)) {
                $url = $url . '?' . http_build_query($query, '', '&amp;');
            }
            // return url
            return $url;
        }
        return false;
    }

    /**
     * Get config data
     *
     * @param $path
     * @return mixed
     */
    protected function _getConfigData($path)
    {
        // return $this->scopeConfig->getValue('payment/' . \Afterpay\Afterpay\Model\Payovertime::METHOD_CODE . '/' . $path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return $this->scopeConfig->getValue('payment/' . \Mony\Mony\Model\Payment::CODE . '/' . $path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get config for merchant id
     *
     * @return mixed
     */
    public function getMerchantId()
    {
        return $this->_getConfigData(self::MERCHANT_ID_XML_NODE);
    }

    /**
     * Get config for merchant id
     *
     * @return mixed
     */
    public function getMerchantSecret()
    {
        return $this->_getConfigData(self::MERCHANT_SECRET_XML_NODE);
    }

    /**
     * Get config for merchant key
     *
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->_getConfigData(self::API_KEY_XML_NODE);
    }

    /**
     * @return bool
     */
    public function isDebugEnabled()
    {
        return (bool)(int)$this->_getConfigData(self::DEBUG_MODE);
    }

    /**
     * @return bool
     */
    public function isSaveCardsEnabled()
    {
        return (bool)(int)$this->_getConfigData(self::SAVE_CARDS_MODE);
    }
}