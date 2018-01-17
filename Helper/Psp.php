<?php

namespace PayEx\Payments\Helper;

use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use GuzzleHttp;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Psp extends AbstractHelper
{
    /**
     * @var GuzzleHttp\Client
     */
    private $client;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var string
     */
    private $merchantToken;

    /**
     * @var string
     */
    private $backendApiUrl;

    /**
     * Psp constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context
    ) {

        parent::__construct($context);
        $this->client = new \GuzzleHttp\Client();
        $this->logger = $context->getLogger();
        $this->remoteAddress = $context->getRemoteAddress();

        // Configure Logger
        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler(BP . '/var/log/payex_psp.log'));
    }

    /**
     * Logger
     *
     * @param       $message
     * @param array $context
     */
    public function log($message, $context = [])
    {
        if (!is_string($message)) {
            $message = var_export($message, true);
        }

        $this->logger->debug($message, $context);
    }

    /**
     * Set Merchant Token
     *
     * @param $merchantToken
     */
    public function setMerchantToken($merchantToken)
    {
        $this->merchantToken = $merchantToken;
    }

    /**
     * Set Backend Api Url
     *
     * @param $backendApiUrl
     */
    public function setBackendApiUrl($backendApiUrl)
    {
        $this->backendApiUrl = $backendApiUrl;
    }

    /**
     * Get Backend Api Url
     *
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
        if (mb_substr($url, 0, 1, 'UTF-8') === '/') {
            $url = $this->backendApiUrl . $url;
        }

        $this->log('Request', [$method, $url, json_encode($params)]);

        // Session ID
        $session_id = Uuid::uuid5(Uuid::NAMESPACE_OID, uniqid());

        // Get Payment URL
        try {
            $headers = [
                'Accept'        => 'application/json',
                'Session-Id'    => $session_id,
                'Forwarded'     => $this->remoteAddress->getRemoteAddress(),
                'Authorization' => 'Bearer ' . $this->merchantToken
            ];

            $response = $this->client->request($method, $url, count($params) > 0 ? array(
                'json'    => $params,
                'headers' => $headers
            ) : array('headers' => $headers));
            $responseBodyAsString = $response->getBody()->getContents();
            $this->log('Response', [$response->getStatusCode(), $responseBodyAsString]);
            if (floor($response->getStatusCode() / 100) != 2) {
                throw new \Exception('Request failed. Status code: ' . $response->getStatusCode());
            }

            // Try to decode
            $result = @json_decode($responseBodyAsString, true);
            if (!$result) {
                $result = $responseBodyAsString;
            }

            return $result;
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            $this->log('ClientException', [$response->getStatusCode(), $responseBodyAsString]);

            // @todo Improve error message with json_decode
            throw new \Exception($responseBodyAsString);
        } catch (GuzzleHttp\Exception\ServerException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            $this->log('ServerException', [$response->getStatusCode(), $responseBodyAsString]);

            // @todo Improve error message with json_decode
            throw new \Exception($responseBodyAsString);
        } catch (\Exception $e) {
            $this->log('Exception', [$e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Extract operation value from operations list
     *
     * @param array  $operations
     * @param string $operation_id
     * @param bool   $single
     *
     * @return bool|string|array
     */
    public function get_operation($operations, $operation_id, $single = true)
    {
        $operation = array_filter($operations, function ($value, $key) use ($operation_id) {
            return (is_array($value) && $value['rel'] === $operation_id);
        }, ARRAY_FILTER_USE_BOTH);

        if (count($operation) > 0) {
            $operation = array_shift($operation);

            return $single ? $operation['href'] : $operation;
        }

        return false;
    }

    /**
     * Filter data source by conditionals array
     *
     * @param array $source
     * @param array $conditionals
     * @param bool  $single
     *
     * @return array|bool
     */
    public function filter(array $source, array $conditionals, $single = true)
    {
        $data = array_filter($source, function ($data, $key) use ($conditionals) {
            $status = true;
            foreach ($conditionals as $ckey => $cvalue) {
                if ( ! isset($data[$ckey]) || $data[$ckey] != $cvalue) {
                    $status = false;
                    break;
                }
            }

            return $status;
        }, ARRAY_FILTER_USE_BOTH);

        if (count($data) === 0) {
            return $single ? false : array();
        }

        return $single ? array_shift($data) : $data;
    }

    /**
     * Init PayEx Checkout Session
     * @return mixed
     * @throws \Exception
     */
    public function init_payment_session()
    {
        // Init Session
        $session = $this->request('GET', '/psp/checkout');
        if (!$session['authorized']) {
            throw new \Exception( 'Unauthorized' );
        }

        return $session['paymentSession'];
    }
}
