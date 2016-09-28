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
class CardTypes implements \Magento\Framework\Option\ArrayInterface
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
        $cardTypes = $this->objectManager->create('Mony\Mony\Model\Adapter\CardTypes');

        // looping all data from api modes
        foreach ($cardTypes->getAllCardTypes() as $name => $cctypes) {
            array_push(
                $result,
                array(
                    'value' => $name,
                    'label' => $cctypes['label'],
                )
            );
        }

        // get the result
        return $result;
    }
}