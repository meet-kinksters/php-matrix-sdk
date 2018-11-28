<?php

namespace Aryess\PhpMatrixSdk;

use Aryess\PhpMatrixSdk\Exceptions\MatrixException;
use Aryess\PhpMatrixSdk\Exceptions\MatrixHttpLibException;
use Aryess\PhpMatrixSdk\Exceptions\MatrixRequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Contains all raw Matrix HTTP Client-Server API calls.
 * For room and sync handling, consider using MatrixClient.
 *
 * Examples:
 *      Create a client and send a message::
 *
 *      $matrix = new MatrixHttpApi("https://matrix.org", $token="foobar");
 *      $response = $matrix.sync();
 *      $response = $matrix->sendMessage("!roomid:matrix.org", "Hello!");
 *
 * @package Aryess\PhpMatrixSdk
 */
class MatrixHttpApi {

    const MATRIX_V2_API_PATH = "/_matrix/client/r0";
    const VERSION = '0.0.1-dev';

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string|null
     */
    private $token;

    /**
     * @var string|null
     */
    private $identity;

    /**
     * @var int
     */
    private $default429WaitMs;

    /**
     * @var bool
     */
    private $useAuthorizationHeader;

    /**
     * @var int
     */
    private $txnId;

    /**
     * @var bool
     */
    private $vallidateCert;

    /**
     * @var Client
     */
    private $client;

    /**
     * MatrixHttpApi constructor.
     *
     * @param string $baseUrl The home server URL e.g. 'http://localhost:8008'
     * @param string|null $token Optional. The client's access token.
     * @param string|null $identity Optional. The mxid to act as (For application services only).
     * @param int $default429WaitMs Optional. Time in milliseconds to wait before retrying a request
     *      when server returns a HTTP 429 response without a 'retry_after_ms' key.
     * @param bool $useAuthorizationHeader Optional. Use Authorization header instead of access_token query parameter.
     * @throws MatrixException
     */
    public function __construct(string $baseUrl, ?string $token = null, ?string $identity = null,
                                int $default429WaitMs = 5000, bool $useAuthorizationHeader = true) {
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new MatrixException("Invalid homeserver url $baseUrl");
        }

        if (!array_get(parse_url($baseUrl), 'scheme')) {
            throw new MatrixException("No scheme in homeserver url $baseUrl");
        }
        $this->baseUrl = $baseUrl;
        $this->token = $token;
        $this->identity = $identity;
        $this->txnId = 0;
        $this->vallidateCert = true;//FIXME: use config
        $this->client = new Client();
        $this->default429WaitMs = $default429WaitMs;
        $this->useAuthorizationHeader = $useAuthorizationHeader;
    }

    public function setClient(Client $client) {
        $this->client = $client;
    }

    /**
     * @param string|null $since Optional. A token which specifies where to continue a sync from.
     * @param int $timeoutMs
     * @param null $filter
     * @param bool $fullState
     * @param string|null $setPresence
     * @return array|string
     * @throws MatrixException
     * @throws GuzzleException
     */
    public function sync(?string $since = null, int $timeoutMs = 30000, $filter = null,
                         bool $fullState = false, ?string $setPresence = null) {
        $request = [
            'timeout' => (int)$timeoutMs,
        ];

        if ($since) {
            $request['since'] = $since;
        }

        if ($filter) {
            $request['filter'] = $filter;
        }

        if ($fullState) {
            $request['full_state'] = json_encode($fullState);
        }

        if ($setPresence) {
            $request['set_presence'] = $setPresence;
        }

        return $this->send('GET', "/sync", null, $request);
    }

    public function validateCertificate(bool $validity) {
        $this->vallidateCert = $validity;
    }

    /**
     * Performs /register.
     *
     * @param array $authBody Authentication Params.
     * @param string $kind Specify kind of account to register. Can be 'guest' or 'user'.
     * @param bool $bindEmail Whether to use email in registration and authentication.
     * @param string|null $username The localpart of a Matrix ID.
     * @param string|null $password The desired password of the account.
     * @param string|null $deviceId ID of the client device.
     * @param string|null $initialDeviceDisplayName Display name to be assigned.
     * @param bool $inhibitLogin Whether to login after registration. Defaults to false.
     */
    public function register(array $authBody = [], string $kind = "user", bool $bindEmail = false,
                             ?string $username = null, ?string $password = null, ?string $deviceId = null,
                             ?string $initialDeviceDisplayName = null, bool $inhibitLogin = false) {
        $content = [
            'kind' => $kind
        ];
        if ($authBody) {
            $content['auth'] = $authBody;
        }
        if ($username) {
            $content['username'] = $username;
        }
        if ($password) {
            $content['password'] = $password;
        }
        if ($deviceId) {
            $content['device_id'] = $deviceId;
        }
        if ($initialDeviceDisplayName) {
            $content['initial_device_display_name'] = $initialDeviceDisplayName;
        }
        if ($bindEmail) {
            $content['bind_email'] = $bindEmail;
        }
        if ($inhibitLogin) {
            $content['inhibit_login'] = $inhibitLogin;
        }

        return $this->send('POST', '/register', $content, ['kind' => $kind]);
    }

    public function getDisplayName(string $userId): ?string {
        $content = $this->send("GET", "/profile/$userId/displayname");
        return array_get($content, 'displayname', json_encode($content));
    }

    /**
     * @param string $method
     * @param string $path
     * @param mixed $content
     * @param array $queryParams
     * @param array $headers
     * @param string $apiPath
     * @param bool $returnJson
     * @return array|string
     * @throws MatrixException
     */
    private function send(string $method, string $path, $content = null, array $queryParams = [], array $headers = [],
                          $apiPath = self::MATRIX_V2_API_PATH, $returnJson = true) {
        $options = [];
        if (!in_array('User-Agent', $headers)) {
            $headers['User-Agent'] = 'php-matrix-sdk/'.self::VERSION;
        }

        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'])) {
            throw new MatrixException("Unsupported HTTP method: $method");
        }

        if (!in_array('Content-Type', $headers)) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($this->useAuthorizationHeader) {
            $headers['Authorization'] = sprintf('Bearer %s', $this->token);
        } else {
            $queryParams['access_token'] = $this->token;
        }

        if ($this->identity) {
            $queryParams['user_id'] = $this->identity;
        }

        $endpoint = $this->baseUrl . $apiPath . $path;
        if ($headers['Content-Type'] == "application/json" && $content != null) {
            $content = json_encode($content);
        }

        $options = array_merge($options, [
            'headers' => $headers,
            'query' => $queryParams,
            'body' => $content,
            'verify' => $this->vallidateCert,
        ]);

        $responseBody = '';
        while (true) {
            try {
                $response = $this->client->request($method, $endpoint, $options);
            } catch (GuzzleException $e) {
                throw new MatrixHttpLibException($e, $method, $endpoint);
            }

            if ($response->getStatusCode() != 429) {
                $responseBody = $response->getBody()->getContents();
                break;
            }

            $jsonResponse = json_decode($responseBody, true);
            $waitTime = array_get($jsonResponse, 'retry_after_ms');
            $waitTime = $waitTime ?: array_get($jsonResponse, 'error.retry_after_ms', $this->default429WaitMs);
            $waitTime /= 1000;
            sleep($waitTime);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new MatrixRequestException($response->getStatusCode(), $responseBody);
        }

        return $returnJson ? json_decode($responseBody, true) : $responseBody;
    }
}