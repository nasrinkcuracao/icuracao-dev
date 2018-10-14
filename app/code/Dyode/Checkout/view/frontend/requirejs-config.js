/**
 * Dyode_Checkout Module
 *
 * Extending Magento_Checkout shipping core js file
 *
 * @module    Dyode_Checkout
 * @author    Mathew Joseph <mathew.joseph@dyode.com>
 * @copyright Copyright © Dyode
 */
var config = {
    config: {
        mixins: {
            'Magento_Tax/js/view/checkout/summary/shipping': {
                'Dyode_Checkout/js/mixin/summary-shipping-mixin': true
            }
        }
    }
};
