<?php
/**
 * Magento 2 extensions for Mony Payment
 *
 * @author Mony <steven.gunarso@touchcorp.com>
 * @copyright 2016 Mony https://www.monypayments.com.au/
 */
namespace Mony\Mony\Helper;

use \Mony\Mony\Model\Logger\Logger as MonyLogger;
use \Mony\Mony\Model\Config\Mony as MonyConfig;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_logger;

    /**
     * @var MonyConfig $monyConfig
     */
    protected $monyConfig;

    /**
     * Config constructor.
     *
     * @param Template\Context $context
     * @param MonyLogger $logger
     * @param MonyConfig $monyConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        MonyLogger $logger,
        MonyConfig $monyConfig
    ) {
        parent::__construct($context);
        $this->_logger = $logger;
        $this->monyConfig = $monyConfig;
    }

    public function debug($message, array $context = array())
    {
        if ($this->monyConfig->isDebugEnabled()) {
            return $this->_logger->debug($message, $context);
        }
    }
}