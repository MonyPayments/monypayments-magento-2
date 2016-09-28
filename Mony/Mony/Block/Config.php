<?php

namespace Mony\Mony\Block;

use Magento\Framework\View\Element\Template;
use Mony\Mony\Model\Config\Mony as MonyConfig;

class Config extends Template
{
    /**
     * @var MonyConfig $monyConfig
     */
    protected $monyConfig;

    /**
     * Config constructor.
     *
     * @param Payovertime $payovertime
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        MonyConfig $monyConfig,
        Template\Context $context,
        array $data
    )
    {
        $this->monyConfig = $monyConfig;

        parent::__construct($context, $data);
    }

    protected function _construct()
    {
        parent::_construct();

        return $this;
    }

    /**
     * Get URL to mony.js
     *
     * @return bool|string
     */
    public function getMonyJsUrl()
    {
        return $this->monyConfig->getWebUrl('mony.js');
    }

    /**
     * Get API Key to mony.js
     *
     * @return bool|string
     */
    public function getMonyApiKey()
    {
        return $this->monyConfig->getApiKey();
    }
}