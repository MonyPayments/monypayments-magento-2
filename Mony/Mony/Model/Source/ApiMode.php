<?php
/**
 * Magento 2 extensions for Mony Payment
 *
 * @author Mony <steven.gunarso@touchcorp.com>
 * @copyright 2016 Mony https://www.monypayments.com.au/
 */
namespace Mony\Mony\Model\Source;

/**
 * Class ApiMode
 * @package Mony\Mony\Model\Source
 */
class ApiMode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * protected object manager
     */
    protected $objectManager;

    /**
     * ApiMode constructor.
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $result = [];
        // get api mode model to get from XML
        $apiMode = $this->objectManager->create('Mony\Mony\Model\Adapter\ApiMode');

        // looping all data from api modes
        foreach ($apiMode->getAllApiModes() as $name => $environment) {
            array_push(
                $result,
                array(
                    'value' => $name,
                    'label' => $environment['label'],
                )
            );
        }

        // get the result
        return $result;
    }
}