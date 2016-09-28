<?php

namespace Mony\Mony\Block;

use \Magento\Framework\View\Element\Template;
use \Mony\Mony\Model\Config\Mony as MonyConfig;
use \Mony\Mony\Model\Data\Customer as MonyCustomer;

class Card extends Template
{
    /**
     * @var MonyConfig $monyConfig
     */
    protected $monyConfig;

    /**
     * @var MonyCustomer $monyCustomer
     */
    protected $monyCustomer;

    /**
     * Config constructor.
     *
     * @param Payovertime $payovertime
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        MonyConfig $monyConfig,
        MonyCustomer $monyCustomer,
        array $data
    )
    {
        $this->monyConfig = $monyConfig;
        $this->monyCustomer = $monyCustomer;

        parent::__construct($context, $data);
    }

    protected function _construct()
    {
        parent::_construct();
        return $this;
    }

    public function getUserLoggedIn() {
        $customer_session = $this->monyCustomer->getCustomerFromSession();

        if( !empty( $customer_session ) ) {
            return true;
        }
        else {
            return false;
        }
    }

    public function getCanSaveCards() {
        return $this->monyConfig->isSaveCardsEnabled();
    }

    public function getSavedCardsFrontend() {
        $customer_session = $this->monyCustomer->getCustomerFromSession();

        if( !empty( $customer_session ) && $this->monyConfig->isSaveCardsEnabled()  ) {
            $saved_cards = $this->monyCustomer->getSavedCards();

            if( empty($saved_cards) || count($saved_cards) < 1 ) {
                return array();
            }
            else {
                return $this->formatCardDisplay( $saved_cards );
            }
        }
        else {
            return array();
        }
    }

    public function getSavedCards() {
        $customer_session = $this->monyCustomer->getCustomerFromSession();

        if( !empty( $customer_session ) && $this->monyConfig->isSaveCardsEnabled() ) {
            $saved_cards = $this->monyCustomer->getSavedCards();
            return $saved_cards;
        }
        else {
            return array();
        }
    }

    public function getDeleteUrl( $token ) {
        $base_url = $this->getBaseUrl();
        $delete_url = $base_url . "mony/card/saved" ."?token=" . $token . "&delete=1";

        return $delete_url;
    }

    public function formatCardDisplay( $saved_cards ) {
        foreach( $saved_cards as $key => $card ) {
            $saved_cards[$key]["display"] = $this->getCardBrand($card) . ' ' . $this->getCardNumber($card) 
                . ' expires: ' . $this->getCardExpiryDate($card);
        }

        //add the 'new card' handling
        $saved_cards[] =    array(
                                "display"   =>  "Add new Card",
                                "token"     =>  "new"
                            );

        return $saved_cards;
    }

    private function getCardBrand($card) {
        return $card["brand"];
    }

    private function getCardNumber($card) {
        return "**** **** **** " . $card["truncatedNumber"];
    }

    private function getCardExpiryDate($card) {
        return sprintf( "%02d", $card["expiryMonth"] ) . " / " . $card["expiryYear"];
    }
}