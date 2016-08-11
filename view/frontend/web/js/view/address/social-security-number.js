/*jshint browser:true jquery:true*/
/*global alert*/
define([
    'ko',
    'uiComponent',
    'PayEx_Payments/js/action/get-social-security-number'
], function (ko, Component, getSocialSecurityNumberAction) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'PayEx_Payments/address/social-security-number'
        },
        initialize: function () {
            this._super();
            return this;
        },

        /**
         * Get Address by SSN
         */
        getAddress: function() {
            getSocialSecurityNumberAction();
            return this;
        }
    });
});
