/**
 * Mony Payments Magento JS component
 *
 * @category    Mony
 * @package     Mony_Mony
 * @author      Mony
 * @copyright   Mony (http://monypayments.com.au)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/*browser:true*/
/*global define*/
define(
    [
        window.MonyJS.MonyJsUrl,
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        MonyJS,
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push({
            type: 'mony_mony',
            component: 'Mony_Mony/js/view/payment/method-renderer/mony-method'
        });
        /** Add view logic here if needed */
        return Component.extend({});
    }
);