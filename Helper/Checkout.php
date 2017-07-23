<?php

namespace PayEx\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use GuzzleHttp;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Checkout extends AbstractHelper
{
	protected $client;

	protected $merchantToken;

	protected $backendApiUrl;

    /**
     * Checkout constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param GuzzleHttp\Client                     $client
     */
	public function __construct(
		\Magento\Framework\App\Helper\Context $context,
		GuzzleHttp\Client $client
	) {

		parent::__construct($context);
		$this->client = $client;
	}

    /**
     * Set Merchant Token
     * @param $merchantToken
     */
	public function setMerchantToken($merchantToken)
    {
	    $this->merchantToken = $merchantToken;
    }

    /**
     * Set Backend Api Url
     * @param $backendApiUrl
     */
    public function setBackendApiUrl($backendApiUrl)
    {
        $this->backendApiUrl = $backendApiUrl;
    }

    /**
     * Get Backend Api Url
     * @return mixed
     */
    public function getBackendApiUrl()
    {
        return $this->backendApiUrl;
    }

    /**
	 * Do API Request
	 *
	 * @param       $method
	 * @param       $url
	 * @param array $params
	 *
	 * @return array|mixed|object
	 * @throws \Exception
	 */
	public function request($method, $url, $params = [])
    {
	    // @todo Add Logger

		// Get Payment URL
		try {
			$headers = [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->merchantToken
            ];

			$response = $this->client->request($method, $url, count($params) > 0 ? [
                'json' => $params,
                'headers' => $headers
            ] : ['headers' => $headers]);

			if (floor($response->getStatusCode() / 100) != 2) {
				throw new \Exception('Request failed. Status code: ' . $response->getStatusCode());
			}

			$response = $response->getBody()->getContents();

			return json_decode($response, TRUE);
		} catch ( GuzzleHttp\Exception\ClientException $e ) {
			$response = $e->getResponse();
			$responseBodyAsString = $response->getBody()->getContents();

			throw new \Exception($responseBodyAsString);
		} catch (GuzzleHttp\Exception\ServerException $e) {
			$response             = $e->getResponse();
			$responseBodyAsString = $response->getBody()->getContents();

			throw new \Exception( $responseBodyAsString );
		} catch ( \Exception $e ) {
			throw $e;
		}
	}

	/**
	 * @return mixed
	 * @throws \Exception
	 */
	public function init_payment_session()
    {
		// Init Session
		$session = $this->request( 'GET', $this->backendApiUrl . '/psp/checkout' );
		if ( ! $session['authorized'] ) {
			throw new \Exception( 'Unauthorized' );
		}

		return $session['paymentSession'];
	}

    /**
     * @param       $payment_session_url
     * @param array $params
     *
     * @return array|mixed|object
     */
	public function init_payment($payment_session_url, array $params)
    {
		// Get Payment URL
		$result = $this->request( 'POST', $payment_session_url, $params );
		return $result;
	}
}
