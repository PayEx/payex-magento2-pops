/*jshint browser:true jquery:true*/
/*global alert*/
define([
    'ko',
    'uiComponent',
    'PayEx_Payments/js/action/get-social-security-number'
], function (ko, Component, getSocialSecurityNumberAction) {
    'use strict';
    var isEnabled = window.checkoutConfig.payexSSN.isEnabled;
    var appliedSSN = window.checkoutConfig.payexSSN.appliedSSN;

    return Component.extend({
        defaults: {
            template: 'PayEx_Payments/address/social-security-number'
        },
        initialize: function () {
            this._super();
            return this;
        },

        /**
         * Is Displayed
         */
        isDisplayed: function () {
            return isEnabled;
        },

        /**
         * Get Address by SSN
         */
        getAddress: function () {
            getSocialSecurityNumberAction();
            return this;
        },

        /**
         * Is Applied SSN
         */
        isAppliedSSN: function () {
            return !!appliedSSN;
        },

        /**
         * Get Applied SSN
         */
        getAppliedSSN: function () {
            return appliedSSN;
        }
    });
});
