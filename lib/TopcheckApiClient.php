<?php
/**
 * TopCheck Loans API Client library
 *
 * Pure PHP based API client library used to communicate Loan Order processes on the go.
 * It allows to push updated order/lead statuses over TopCheck RESTful API server.
 *
 * @package Topcheck\ApiClient
 * @version 1.0.0-alpha
 * @author Topcheck UG (haftungsbeschränkt) <info@topcheck.com>
 * @license LGPLv3
 * @license https://opensource.org/licenses/LGPL-3.0
 */
namespace Topcheck\ApiClient;

/**
 * This class is the main communication class for the API library.
 *
 * Please refer to constructor and public methods for usage.
 */
class ApiClient
{
    /** @var string|null Should contain the client's login name */
    private $login;

    /** @var string|null Should contain the client's login password */
    private $password;

    /** @var string Will contain the REST API server's URL */
    private $serverUrl;

    /** @var bool Indicates whether we're running on a test server or not */
    private $serverTest;

    /** @var string|null Should contain oAuth API token granted by server */
    private $token;

    /** @var string|null Should contain basic auth login for given test server */
    private $htLogin;

    /** @var string|null Should contain basic auth password for given test server */
    private $htPass;

    /** @var static array Contains default error response codes together with their descriptions */
    private static $aResponseCodes = [
        'INTERNAL_UNABLE_TO_LOGIN' => 'Unable to send login request.',
        'INTERNAL_UNABLE_TO_SEND'  => 'Unable to send request.',
        'INTERNAL_EMPTY_TCID'      => 'Empty TcId.'
    ];

    /**
     * Constructor, builds the object.
     *
     * @param string $login The login for TopCheck API server.
     * @param string $password The password for TopCheck API server.
     * @param array|null $testServer Optional. Array of key-value pairs with test server info.
     *                   Array should contains at least one key and two optional keys.
     *                   'host'    - Full address (including protocol) of the test server to be used (required).
     *                               Production one will be used if none provided.
     *                   'htlogin' - Login for basic auth (optional).
     *                   'htpass'  - Pass for basic auth (optional).
     */
    public function __construct($login, $password, $testServer = null)
    {
        $this->login    = $login;
        $this->password = $password;
        $this->token    = null;
        $this->htLogin  = null;
        $this->htPass   = null;

        $this->serverTest = false;
        $server           = 'https://topcheck.com.ng';

        if (!is_null($testServer) and is_array($testServer) and !empty($testServer) and isset($testServer['host'])) {

            $this->serverTest = true;
            $server           = $testServer['host'];
            if (isset($testServer['htlogin']) and isset($testServer['htpass'])) {
                $this->htLogin = $testServer['htlogin'];
                $this->htPass  = $testServer['htpass'];
            }
        }

        $this->serverUrl = sprintf('%s/api/v1/', $server);
        unset($server);
    }

    /**
     * Send login request.
     *
     * @api
     * @return array The decoded response in an array format OR an error response.
     */
    public function login()
    {
        $this->token = null;

        $data = json_encode([
            'login'    => $this->login,
            'password' => $this->password
        ]);

        $result = $this->postData($this->serverUrl . 'login', $data);
        if ($result === false) {
            return $this->generateErrorResponseArray('INTERNAL_UNABLE_TO_LOGIN');
        }

        $decoded = json_decode($result, true);

        if ($this->isResponseSuccess($decoded)) {
            $this->token = $decoded['access_token'];
        }
        return $decoded;
    }

    /**
     * Sets certain order's progress status.
     *
     * @api
     * @param string $tcId TopCheck ID of the order to manipulate.
     * @param array $aStatus Key-boolean pair of statuses to set on certain order. The status keys may include:
     *                     'isBvnSuccessful', 'isCbSuccessful', 'isBsSuccessful', 'isAddressCorrect', 'isDocumentationComplete'
     * @return array $result Key-value pair of response status. In case of success at least one key is included: ['status' => 'SUCCESS']
     *                       otherwise array with error code and description  is returned: ['status' => 'ERROR', 'errorCode' => 'Error code',
     *                       'msg'       => 'Error decsription' ].
     */
    public function setSingleProductStatus($tcId, $aStatus)
    {
        if (empty($tcId)) {
            return $this->generateErrorResponseArray('INTERNAL_EMPTY_TCID');
        }

        $result = $this->executePost($this->serverUrl . 'loans/' . $tcId, json_encode($aStatus));

        if (is_array($result) and array_key_exists('errorCode', $result) and $result['errorCode'] === 'TOKEN_NOT_EXIST') {
            $this->token = null;
            $result      = $this->executePost($this->serverUrl . 'loans/' . $tcId, json_encode($aStatus));
        }

        return $result;
    }

    /**
     * Sets certain order's conversion flag status.
     *
     * @api
     * @param string $tcId TopCheck ID of the order to manipulate.
     * @param array $aInfo Key-value pair of aditional information to set on certain order. The status keys has to include:
     *                     'loanAmountGranted' - (numeric value), 'tenure' - (numeric value), 'conversionDate' - (date in format YYYY-MM-DD)
     * @return array $result Key-value pair of response status data. In case of success at least one key is included: ['status' => 'SUCCESS']
     *                       otherwise array with error code and description  is returned: ['status' => 'ERROR', 'errorCode' => 'Error code',
     *                       'msg'       => 'Error decsription' ].
     */
    public function setSingleProductConverted($tcId, $aInfo)
    {
        if (empty($tcId)) {
            return $this->generateErrorResponseArray('INTERNAL_EMPTY_TCID');
        }

        $result = $this->executePost($this->serverUrl . 'loans/' . $tcId . '/convert', json_encode($aInfo));

        if (is_array($result) and array_key_exists('errorCode', $result) and $result['errorCode'] === 'TOKEN_NOT_EXIST') {
            $this->token = null;
            $result      = $this->executePost($this->serverUrl . 'loans/' . $tcId . '/convert', json_encode($aInfo));
        }

        return $result;
    }

    /**
     * Sets certain order's rejected flag status.
     *
     * @api
     * @param string $tcId TopCheck ID of the order to manipulate.
     * @return array $result Key-value pair of response status. In case of success at least one key is included: ['status' => 'SUCCESS']
     *                       otherwise array with error code and description  is returned: ['status' => 'ERROR', 'errorCode' => 'Error code',
     *                       'msg'       => 'Error decsription' ].
     */
    public function setSingleProductRejected($tcId)
    {
        if (empty($tcId)) {
            return $this->generateErrorResponseArray('INTERNAL_EMPTY_TCID');
        }

        $result = $this->executePost($this->serverUrl . 'loans/' . $tcId . '/reject', '');

        if (is_array($result) and array_key_exists('errorCode', $result) and $result['errorCode'] === 'TOKEN_NOT_EXIST') {
            $this->token = null;
            $result      = $this->executePost($this->serverUrl . 'loans/' . $tcId . '/reject', '');
        }

        return $result;
    }

    /**
     * Generate standarized error output and human-readable error message from $errorKey variable.
     *
     * @param string $errorKey Hardcoded error code.
     * @return array An error formatted in an array format with errorCode and msg keys.
     */
    private function generateErrorResponseArray($errorKey)
    {
        return [
            'status'    => 'ERROR',
            'errorCode' => $errorKey,
            'msg'       => self::$aResponseCodes[$errorKey]
        ];
    }

    /**
     * Check if response is succesfull.
     *
     * @param array $decodedResponse Decoded response from previous methods.
     * @return bool Success state of the response.
     */
    private function isResponseSuccess($decodedResponse)
    {
        if (is_array($decodedResponse) and array_key_exists('status', $decodedResponse) and $decodedResponse['status'] === 'SUCCESS') {
            return true;
        }
        return false;
    }

    /**
     * Initialize curl object.
     *
     * @param bool $isPost Flag indicates if curl is initialised as POST request or GET request. Default GET.
     * @return resource Curl object.
     */
    private function initCurl($isPost = false)
    {
        $curl = curl_init();

        // set default options
        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POST           => $isPost,
            CURLOPT_RETURNTRANSFER => true
        ]);

        // check if we're running on a test server
        if ($this->serverTest) {

            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        }

        // set HTTP headers
        $curlHeaders = [];
        if (!is_null($this->htLogin) and !is_null($this->htPass)) {
            $curlHeaders = [
                'authorization: Basic ' . base64_encode($this->htLogin . ':' . $this->htPass),
                'Content-type: application/json'
            ];
        }
        if (!is_null($this->token)) {
            $curlHeaders[] = 'access-token: ' . $this->token;
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $curlHeaders);

        return $curl;
    }

    /**
     * Send curl request.
     * Attempts to send curl request three times in case of laggy connections.
     *
     * @param resource Curl object created previously.
     * @return bool|array Logical false if connection timed out, array with curl response otherwise.
     */
    private function sendCurl($curl)
    {
        // make few attempts
        for ($i = 0; $i < 3; $i++) {
            $result = curl_exec($curl);
            if ($result !== false) {
                curl_close($curl);
                return $result;
            }
        }
        curl_close($curl);
        return false;
    }

    /**
     * Sends API command.
     * Utilizes curl to send HTTP POST request to server.
     *
     * @param string $url The URL address where the request should be made.
     * @param string $body Data to be send
     * @return bool|array Logical false if connection timed out, array with curl response otherwise.
     */
    private function postData($url, $body)
    {
        $curl = $this->initCurl(true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        return $this->sendCurl($curl);
    }

    /**
     * Receives API command.
     * Utilizes curl to send HTTP GET request to server.
     *
     * @param string $url The URL address where the request should be made.
     * @return bool|array Logical false if connection timed out, array with curl response otherwise.
     */
    private function getData($url)
    {
        $curl = $this->initCurl(false);
        curl_setopt($curl, CURLOPT_URL, $url);
        return $this->sendCurl($curl);
    }

    /**
     * Checks if logged in and post data on given $url.
     *
     * @param string $url Address url where to post data
     * @param string $jsonEncoded Json encoded data to send
     * @return array $result Key-value pair of response status. In case of success at least one key is included: ['status' => 'SUCCESS']
     *                       otherwise array with error code and description  is returned: ['status' => 'ERROR', 'errorCode' => 'Error code',
     *                       'msg'       => 'Error decsription' ].
     */
    private function executePost($url, $jsonEncoded)
    {
        // if we don't have token then get new one
        if (is_null($this->token)) {
            $result = $this->login();
            if (!$this->isResponseSuccess($result)) {
                return $result;
            }
        }

        $result = $this->postData($url, $jsonEncoded);
        if ($result === false) {
            return $this->generateErrorResponseArray('INTERNAL_UNABLE_TO_SEND');
        }

        return json_decode($result, true);
    }
}
