<?php
/**
 * Magento 2 extensions for Mony Payment
 *
 * @author Mony <steven.gunarso@touchcorp.com>
 * @copyright 2016 Mony https://www.monypayments.com.au/
 */
namespace Mony\Mony\Model\Adapter\Request;

use \Magento\Framework\HTTP\ZendClientFactory;
use \Mony\Mony\Model\Config\Mony as MonyConfig;
use \Magento\Framework\Json\Helper\Data as JsonHelper;
use \Mony\Mony\Helper\Data as MonyHelper;

/**
 * Class Call
 * @package Mony\Mony\Model\Adapter\Request
 */
class Call
{
    /**
     * @var for HTTP Client
     */
    protected $client;
    protected $monyConfig;
    protected $jsonHelper;
    protected $helper;

    /**
     * Call constructor.
     * @param ZendClientFactory $httpClientFactory
     * @param monyConfig $monyConfig
     * @param JsonHelper $jsonHelper
     * @param MonyHelper $helper
     */
    public function __construct(
        ZendClientFactory $httpClientFactory,
        MonyConfig $monyConfig,
        JsonHelper $jsonHelper,
        MonyHelper $helper
    ) {
        /** HTTP Client and mony config */
        $this->httpClientFactory = $httpClientFactory;
        $this->monyConfig = $monyConfig;
        $this->jsonHelper = $jsonHelper;
        $this->helper = $helper;
    }

    /**
     * Send using HTTP call
     *
     * @param $url
     * @param bool $body
     * @param string $method
     * @return \Zend_Http_Response
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Http_Client_Exception
     */
    public function send($url, $body = false, $method = \Magento\Framework\HTTP\ZendClient::GET)
    {

        if( $method != \Magento\Framework\HTTP\ZendClient::GET && $method != \Magento\Framework\HTTP\ZendClient::POST ) {
            return $this->sendCustom($url, $body, $method);
        }
 
        // set the client http
        $client = $this->httpClientFactory->create();

        // set body and the url
        $client->setUri($url)
            ->setRawData($this->jsonHelper->jsonEncode($body), 'application/json');

        // add auth for API requirements
        $client->setAuth(
            trim($this->monyConfig->getMerchantId()),
            trim($this->monyConfig->getMerchantSecret())
        );

        // set configurations
        $client->setConfig(
            [
                'maxredirects' => 0,
                'useragent'    => 'MonyMagento2Plugin',
                'timeout'      => 80
            ]
        );

        // debug mode
        $requestLog = array(
            'type' => 'Request',
            'method' => $method,
            'url' => $url,
            'body' => $body
        );
        // $this->helper->debug($this->jsonHelper->jsonEncode($requestLog));

        // do the request with catch
        try {
            $response = $client->request($method);

            // debug mode
            $responseLog = array(
                'type' => 'Response',
                'method' => $method,
                'url' => $url,
                'httpStatusCode' => $response->getStatus(),
                'body' => $this->jsonHelper->jsonDecode($response->getBody())
            );
            // $this->helper->debug($this->jsonHelper->jsonEncode($responseLog));

            $responseBody = $this->jsonHelper->jsonDecode($response->getBody());

        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Gateway error: %1', $e->getMessage())
            );
        }

        // return response
        return $responseBody;
    }




    /**
     * Send using HTTP call - DELETE 
     * Request hacks due to Magento 2 ZendClient inability to support CURLOPT_CUSTOMREQUEST
     *
     * @param $url
     * @param bool $body
     * @param string $method
     * @return cUrl Response
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sendCustom($url, $body = false, $method = \Magento\Framework\HTTP\ZendClient::GET)
    {

        $body = $this->jsonHelper->jsonEncode($body);
        $auth = base64_encode( trim($this->monyConfig->getMerchantId()) . ':' . 
                trim($this->monyConfig->getMerchantSecret()) );

        $headers =  array(
                        "Content-Type: application/json", 
                        "Content-Length: " . strlen($body),
                        "Authorization: Basic " . $auth
                    );

        // do the request with catch
        try {
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 80);
            curl_setopt($ch, CURLOPT_USERAGENT, "MonyMagento2Plugin");
            
            curl_setopt($ch, CURLOPT_HEADER, true);   //we want headers
             
            $result = curl_exec($ch);

            $curl_info = curl_getinfo($ch);

            $response["body"] = $result;
            $response["statusCode"] = $curl_info["http_code"];
             
            return $response;

        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Gateway error: %1', $e->getMessage())
            );
        }
    }
}