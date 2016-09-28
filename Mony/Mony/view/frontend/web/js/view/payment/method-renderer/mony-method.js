/**
 * Mony_Mony Magento JS component
 *
 * @category    Mony
 * @package     Mony_Mony
 * @author      Monypayment
 * @copyright   Mony (http://monypayments.com.au)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'Magento_Checkout/js/model/payment/additional-validators'
    ],
    function (Component, $, validator, additionalValidators) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Mony_Mony/payment/mony-form'
            },

            getCode: function() {
                return 'mony_mony';
            },

            getSavedCards: function() {
                return window.MonyJS.MonySavedCards;
            },

            isActive: function() {
                return true;
            },

            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            defaultPaymentAjax: function(process) {
                var parent = this;
                var data;

                //MonySelect will dteermine which token to use
                if( process ) {

                    var cc_number_enc = $("#" + this.getCode() + "-form #cc_number_enc").val();
                    var cc_last4 = $("#" + this.getCode() + "-form #cc_last4").val();
                    var monypayments_token = $("#" + this.getCode() + "-form #monypayments_token").val();

                    if( $("#" + this.getCode() + "_cc_save_card").is(':checked') ) {
                        var cc_save_card = 1;
                    }
                    else {
                        var cc_save_card = 0;
                    }

                    data =  {
                                "cc_number_enc"         :   cc_number_enc,
                                "cc_last4"              :   cc_last4,
                                "monypayments_token"    :   monypayments_token,
                                "cc_save_card"          :   cc_save_card,
                            };
                }
                else {
                    var monypayments_token = $("select[name='payment[payment_select]']").val();
                    data =  {
                                "monypayments_token"    :   monypayments_token,
                            };
                }

                //return save as original step to Ajax
                $.ajax({
                    url: "/mony/payment/process",
                    method:'post',
                    data: data, 
                    success: function(result) {

                        result = $.parseJSON(result);

                        if( result["success"] === true ) {

                            parent.placeOrder();
                        }
                    }
                });
            },
            
            /**
             *  process Mony Payment
             */
            continueMonyPayment: function (response) {

                // Set parent class for submit ajax
                var parentClass = this;

                    // Wrap around save payment details to generate token
                    // if (additionalValidators.validate() ) {


                        // Calculate should process order or not
                        var process = false;
                        var monySelect = false;
                        var monySelect = document.getElementById( this.getCode() + '-payment-select');
                        // var monySelect = false;

                        // If not login and use mony
                        if ( !monySelect.value.length ) {
                            process = true;
                        }
                        // If login and use mony
                        else if (monySelect.value == 'new') {
                            process = true;
                        }

                        // Check if should do the process
                        if (process) {
                            // return error message if Mony is not available
                            if (typeof Mony == 'undefined') {
                                alert('There was an error validating your card. Please try again later');
                            }
                            // if Mony is available
                            else {

                                // Init Mony Function
                                // var moniInit = Mony.init({apiKey: '<?php echo $this->getApiKey() ?>'});
                                var moniInit = Mony.init({apiKey: window.MonyJS.MonyApiKey});

                                // Generate Token using Moni.JS
                                var form = document.getElementById(this.getCode() + "-form");
                                var cardData = {
                                    "name"          :   $("input[name='payment[cc_owner]']").val(),
                                    "number"        :   $("input[name='payment[cc_number]']").val(),
                                    "securityCode"  :   $("input[name='payment[cc_cid]']").val(),
                                    "expiryYear"    :   $("select[name='payment[cc_exp_year]']").val(),
                                    "expiryMonth"   :   $("select[name='payment[cc_exp_month]']").val(),
                                };

                                Mony.createCardToken({"card": cardData}, function(err, response) {
                                    // If error show message to customer
                                    if (err) {
                                        alert(err.message);
                                    } 
                                    else { 
                                        // Set token and date value
                                        var parameters = {
                                            'payment[cc_number_enc]': '',
                                            'payment[cc_last4]': response.card.truncatedNumber,
                                            'payment[monypayments_token]': response.card.token,
                                            //'payment[monypayments_fetched_at]': '<?php echo Mage::getSingleton('core/date')->gmtDate(); // Use UTC time to save to Database ?>'
                                        };

                                        // Added the Mony data to checkout
                                        parentClass.createHiddenInput(parameters, form);

                                        // Start ajax to save
                                        parentClass.defaultPaymentAjax(process);
                                    }
                                });
                            }
                        } 
                        else {
                            parentClass.defaultPaymentAjax(process);
                        }
                    // }
            },


            /* ----------------------------------------------------------------
                                    FROM MONY BASIC
            -----------------------------------------------------------------*/
            /**
             * Create hidden input value for supporting onestep checkout
             *
             * @array Must be an array of key = input name; value = input value
             */
            createHiddenInput: function(array, appendElement)
            {
                // Setting up standard variable
                var input, attr, value;
                var regExp = /\[(.*?)\]/;
                // Looping and create hidden input based on array given
                for (attr in array) {
                    // Check whether key is no null
                    if (array.hasOwnProperty(attr)) {
                        var id = regExp.exec(attr)[1];
                        // Start creating input
                        input = document.createElement('input');
                        input.setAttribute('type', 'hidden');
                        input.setAttribute('id', id);
                        input.setAttribute('name', attr);
                        input.setAttribute('value', array[attr]);

                        // Delete if already exist
                        var element = document.getElementById(id);
                        if (element) {
                            element.parentNode.removeChild(element);
                        }
                        // Adding input to a form
                        appendElement.appendChild(input);
                    }
                }
            },

            showCardForm: function()
            {
                $("#" + this.getCode() + "-form").show();
            },

            hideCardForm: function()
            {
                $("#" + this.getCode() + "-form").hide();
            },

            showMonyCheckoutMid: function()
            {
                $("#mony-saved-cards .mony-checkout").show();
            },

            hideMonyCheckoutMid: function()
            {
                $("#mony-saved-cards .mony-checkout").hide();
            },

            savedCardValueChanged: function() {
                if( $("#" + this.getCode() + "-payment-select" ).length ) {

                    if( $("#" + this.getCode() + "-payment-select" ).val() == "new" ) {
                        this.showCardForm();
                        this.hideMonyCheckoutMid();
                    }
                    else {
                        this.hideCardForm();
                        this.showMonyCheckoutMid();
                    }
                }
            },
        });
    }
);
