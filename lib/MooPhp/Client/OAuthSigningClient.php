<?php
namespace MooPhp\Client;

/**
 * @package MooPhp
 * @author Jonathan Oddy <jonathan@moo.com>
 * @copyright Copyright (c) 2012, Moo Print Ltd.
 */

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class OAuthSigningClient implements Client, LoggerAwareInterface
{

    protected $_apiKey;
    protected $_apiSecret;

    protected $_token;
    protected $_tokenSecret;

    protected $_oauth;

    protected $_ch;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    protected $_marshaller;

    protected $_urls = array(
        'requestToken' => 'https://secure.moo.com/oauth/request_token.php',
        'authorize' => 'https://secure.moo.com/oauth/authorize.php',
        'accessToken' => 'https://secure.moo.com/oauth/access_token.php',
        'apiEndpoint' => 'https://secure.moo.com/api/service/'
    );


    public function __construct($apiKey, $apiSecret, LoggerInterface $logger = null, $endpoint = null)
    {
        $this->_apiKey = $apiKey;
        $this->_apiSecret = $apiSecret;
        $this->_oauth = new \OAuth($apiKey, $apiSecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);
        $this->_oauth->disableSSLChecks(); // Err, why?

        if (isset($logger)) {
            $this->setLogger($logger);
        }
        if (isset($endpoint)) {
            $this->_urls["apiEndpoint"] = $endpoint;
        }

        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    public function getAuthUrl($callback = "oob")
    {
        return $this->_urls["authorize"] . "?oauth_token=" . urlencode($this->_token) . "&oauth_callback=" .
        urlencode($callback);
    }

    public function setToken($token, $secret)
    {
        $this->_token = $token;
        $this->_tokenSecret = $secret;
        $this->_oauth->setToken($token, $secret);
        return $this->getToken();
    }

    public function getToken()
    {
        return array("token" => $this->_token,
            "secret" => $this->_tokenSecret
        );
    }

    public function getRequestToken()
    {
        $token = $this->_oauth->getRequestToken($this->_urls['requestToken']);
        return $this->setToken($token['oauth_token'], $token['oauth_token_secret']);
    }

    public function getAccessToken()
    {
        $token = $this->_oauth->getAccessToken($this->_urls['accessToken']);
        return $this->setToken($token['oauth_token'], $token['oauth_token_secret']);
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

        $target = $this->_urls["apiEndpoint"];

        if ($this->_logger) {
            $this->_logger->debug("Making Request", array("params" => $params));
        }
        switch ($method) {
            case self::HTTP_POST:
                $httpMethod = OAUTH_HTTP_METHOD_POST;
                break;
            case self::HTTP_GET:
                $httpMethod = OAUTH_HTTP_METHOD_GET;
                break;
            default:
                throw new InvalidArgumentException("Invalid http method");
        }

        try {
            $this->_oauth->fetch($target, $params, $httpMethod, array());
            $rawResponse = $this->_oauth->getLastResponse();
        } catch (\OAuthException $e) {
            if (!empty($e->lastResponse)) {
                $rawResponse = $e->lastResponse;
            } else {
                throw $e;
            }
        }

        if ($this->_logger) {
            $this->_logger->debug("Got Response", array("rawResponse" => $rawResponse));
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
        curl_setopt($ch, CURLOPT_URL, $this->_urls["apiEndpoint"]);

        $params[$fileParam] = '@' . $params[$fileParam];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        if ($this->_logger) {
            $this->_logger->debug("Making Request", array("params" => $params));
        }
        $rawResponse = curl_exec($ch);
        if ($this->_logger) {
            $this->_logger->debug("Got Response", array("rawResponse" => $rawResponse));
        }

        $errno = curl_errno($ch);
        if (!$rawResponse || $errno != CURLE_OK) {
            throw new \RuntimeException("Error sending file: ($errno) " . curl_error($ch));
        }

        return $rawResponse;
    }

    public function getFile(array $params)
    {
        $ch = $this->_ch;
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $url = $this->_urls["apiEndpoint"] . '?' . http_build_query($params);

        curl_setopt($ch, CURLOPT_URL, $url);
        if ($this->_logger) {
            $this->_logger->debug("Making Request", array("params" => $params));
        }
        $rawResponse = curl_exec($ch);
        if ($this->_logger) {
            $this->_logger->debug("Got Response", array("rawResponse" => $rawResponse));
        }

        $errno = curl_errno($ch);
        if (!$rawResponse || $errno != CURLE_OK) {
            throw new \RuntimeException("Error retrieving file: ($errno) " . curl_error($ch));
        }

        return $rawResponse;

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
}
