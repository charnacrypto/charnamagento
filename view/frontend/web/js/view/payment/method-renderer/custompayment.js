define(
       [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        'Magento_Catalog/js/price-utils',
        'mage/url'
        ],
       function (
                 $,
                 Component,
                 quote,
                 totals,
                 priceUtils,
                 placeOrderAction,
                 selectPaymentMethodAction,
                 customer,
                 checkoutData,
                 additionalValidators,
                 url) {
       'use strict';
       return Component.extend({
                   defaults: {
                       template: 'CharnaCoin_Custompayment/payment/custompayment'
                             },
                   placeOrder: function () {
                       var redirectUrl = 'charnapayment/Gateway/CharnacoinPayment';
                       console.log(redirectUrl);
                       window.location.replace(url.build(redirectUrl));
                       }
               });
       });
