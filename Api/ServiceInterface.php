<?php

namespace PayEx\Payments\Api;

interface ServiceInterface
{
    /**
     * Returns Redirect Url
     *
     * @api
     * @return string Redirect Url
     */
    public function redirect_url();

    /**
     * Apply Payment Method
     *
     * @api
     * @param string $payment_method
     * @return string
     */
    public function apply_payment_method($payment_method);

    /**
     * Get Terms of Service
     *
     * @api
     * @param string $payment_method
     * @return string
     */
    public function get_service_terms($payment_method);

    /**
     * Get Address by SSN
     * @param string $country_code
     * @param string $postcode
     * @param string $ssn
     *
     * @return string
     */
    public function get_address($country_code, $postcode, $ssn);
}
