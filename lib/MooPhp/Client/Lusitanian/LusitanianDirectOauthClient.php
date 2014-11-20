<?php
namespace MooPhp\Client\Lusitanian;

use MooPhp\Client\Client;
use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth1\Signature\Signature;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

/**
 * @package MooPhp
 * @author Jonathan Oddy <jonathan@moo.com>
 * @copyright Copyright (c) 2012, Moo Print Ltd.
 *
 *
 * Client using the OAuth Signature code from Lusitanian's OAuth library.
 * This does not implement 3 or 2 legged OAuth, only direct, so is not suitable
 * for methods that require proper OAuth.
 * At present pretty much nothing you'll want to use requires 2/3 legged OAuth.
 * If that changes then I might stop being lazy and fix this.
 *
 */
class LusitanianDirectOauthClient implements Client, LoggerAwareInterface
{
    public $_useOpenSSLIfAvailable = true;

    protected $_ch;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var \OAuth\OAuth1\Signature\Signature
     */
    protected $_signature;

    /**
     * @var \OAuth\Common\Consumer\Credentials
     */
    protected $_credentials;

    protected $_endpoint = 'https://secure.moo.com/api/service/';

    protected $_signatureMethod = "HMAC-SHA1";

    /**
     * @var \OAuth\Common\Http\Uri\Uri
     */
    protected $_uri;

    public function __construct($apiKey, $apiSecret, LoggerInterface $logger = null, $endpoint = null)
    {
        $this->_credentials = new Credentials($apiKey, $apiSecret, "oob"); // Lie about oob, we don't need callback.
        $this->_signature = new Signature($this->_credentials);
        $this->_signature->setHashingAlgorithm($this->_signatureMethod);

        if (isset($endpoint)) {
            $this->_endpoint = $endpoint;
        }

        $this->_uri = new Uri($this->_endpoint);

        if (isset($logger)) {
            $this->setLogger($logger);
        }


        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, false); // Why do we need to do this?!
    }

    protected function generateNonce()
    {
        $rawLength = 32;
        if (function_exists('openssl_random_pseudo_bytes') && $this->_useOpenSSLIfAvailable) {
            $byteStr = openssl_random_pseudo_bytes($rawLength);
        } else {
            $byteStr = '';
            for ($i = 0; $i < $rawLength; $i++) {
                $byteStr .= pack('C', mt_rand(0, 256));
            }
        }
        return base64_encode($byteStr);
    }

    protected function _generateAuthHeader($params, $method)
    {
        $oauthParams = array(
            'oauth_consumer_key' => $this->_credentials->getConsumerId(),
            'oauth_signature_method' => $this->_signatureMethod,
            'oauth_timestamp' => date('U'),
            'oauth_nonce' => $this->generateNonce(),
            'oauth_version' => '1.0'
        );

        $signature = $this->_signature->getSignature($this->_uri, array_merge($oauthParams, $params), $method);

        $oauthParams['oauth_signature'] = $signature;

        $authHeaderParts = array();
        foreach ($oauthParams as $key => $value) {
            $authHeaderParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }
        return 'OAuth ' . implode(',', $authHeaderParts);
    }

    /**
     * @param array $params
     * @param string $method
     * @throws \Exception|\OAuthException
     * @throws \InvalidArgumentException
     * @return string
     */
    public function makeRequest(array $params, $method = self::HTTP_POST)
    {

        if ($this->_logger) {
            $this->_logger->debug("Making Request", array("params" => $params));
        }


        $ch = $this->_ch;

        $url = $this->_uri->getAbsoluteUri();

        $authHeader = $this->_generateAuthHeader($params, $method);

        $paramStr = http_build_query($params);

        switch ($method) {
            case self::HTTP_GET:
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                $url .= '?' . $paramStr;
                break;
            case self::HTTP_POST:
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $paramStr);
                break;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,
            CURLOPT_HTTPHEADER,
            array(
                "Authorization: " . $authHeader,
                "Expect:"
            ));

        return $this->_doCurl();
    }

    private function _doCurl()
    {
        $rawResponse = curl_exec($this->_ch);
        if ($this->_logger) {
            $this->_logger->debug("Got Response", array("rawResponse" => $rawResponse));
        }
        $errno = curl_errno($this->_ch);
        if (!$rawResponse || $errno != CURLE_OK) {
            throw new \RuntimeException("Error making request: ($errno) " . curl_error($this->_ch));
        }
        return $rawResponse;
    }

    /**
     * @param array $params
     * @param string $fileParam The param that contains the path on disk to the file
     * @throws \RuntimeException
     * @return mixed
     */
    public function sendFile(array $params, $fileParam)
    {
        $ch = $this->_ch;
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $this->_endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:"));

        $params[$fileParam] = '@' . $params[$fileParam];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        if ($this->_logger) {
            $this->_logger->debug("Making Request", array("params" => $params));
        }
        return $this->_doCurl();
    }

    public function getFile(array $params)
    {
        $ch = $this->_ch;
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:"));

        $url = $this->_endpoint . '?' . http_build_query($params);

        curl_setopt($ch, CURLOPT_URL, $url);
        if ($this->_logger) {
            $this->_logger->debug("Making Request", array("params" => $params));
        }
        return $this->_doCurl();

    }

    /**
     * @param array $paramss Array of arrays of parameters.
     * @param string $fileParam The name of the param that contains the path to the file on disk
     * @return string
     */
    public function sendFiles(array $paramss, $fileParam)
    {
        $responses = array();
        foreach ($paramss as $key => $params) {
            $responses[$key] = $this->sendFile($params, $fileParam);
        }
        return $responses;
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     * @return null
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->_logger = $logger;
    }
}
