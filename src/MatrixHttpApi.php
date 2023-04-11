<?php

namespace MatrixPhp;

use MatrixPhp\Exceptions\MatrixException;
use MatrixPhp\Exceptions\MatrixHttpLibException;
use MatrixPhp\Exceptions\MatrixRequestException;
use MatrixPhp\Exceptions\MatrixUnexpectedResponse;
use MatrixPhp\Exceptions\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

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
 * @see https://matrix.org/docs/spec/client_server/latest
 *
 * @package MatrixPhp
 */
class MatrixHttpApi {

    const MATRIX_V2_API_PATH = '/_matrix/client/r0';
    const MATRIX_V2_MEDIA_PATH = '/_matrix/media/r0';
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
    private $validateCert;

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
        $this->validateCert = true;
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
        $this->validateCert = $validity;
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
     * @return array|string
     * @throws MatrixException
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

    /**
     * Perform /login.
     *
     * @param string $loginType The value for the 'type' key.
     * @param array $args Additional key/values to add to the JSON submitted.
     * @return array|string
     * @throws MatrixException
     */
    public function login(string $loginType, array $args) {
        $args["type"] = $loginType;

        return $this->send('POST', '/login', $args);
    }

    /**
     * Perform /logout.
     *
     * @return array|string
     * @throws MatrixException
     */
    public function logout() {
        return $this->send('POST', '/logout');
    }

    /**
     * Perform /logout/all.
     *
     * @return array|string
     * @throws MatrixException
     */
    public function logoutAll() {
        return $this->send('POST', '/logout/all');
    }

    /**
     * Perform /createRoom.
     *
     * @param string|null $alias Optional. The room alias name to set for this room.
     * @param string|null $name Optional. Name for new room.
     * @param bool $isPublic Optional. The public/private visibility.
     * @param array|null $invitees Optional. The list of user IDs to invite.
     * @param bool|null $federate Optional. Сan a room be federated. Default to True.
     * @return array|string
     * @throws MatrixException
     */
    public function createRoom(string $alias = null, string $name = null, bool $isPublic = false,
                               array $invitees = null, bool $federate = null) {
        $content = [
            "visibility" => $isPublic ? "public" : "private"
        ];
        if ($alias) {
            $content["room_alias_name"] = $alias;
        }
        if ($invitees) {
            $content["invite"] = $invitees;
        }
        if ($name) {
            $content["name"] = $name;
        }
        if ($federate != null) {
            $content["creation_content"] = ['m.federate' => $federate];
        }
        return $this->send("POST", "/createRoom", $content);
    }

    /**
     * Performs /join/$room_id
     *
     * @param string $roomIdOrAlias The room ID or room alias to join.
     * @return array|string
     * @throws MatrixException
     */
    public function joinRoom(string $roomIdOrAlias) {
        $path = sprintf("/join/%s", urlencode($roomIdOrAlias));

        return $this->send('POST', $path);
    }

    /**
     * Perform PUT /rooms/$room_id/state/$event_type
     *
     * @param string $roomId The room ID to send the state event in.
     * @param string $eventType The state event type to send.
     * @param array $content The JSON content to send.
     * @param string $stateKey Optional. The state key for the event.
     * @param int|null $timestamp Set origin_server_ts (For application services only)
     * @return array|string
     * @throws MatrixException
     */
    public function sendStateEvent(string $roomId, string $eventType, array $content,
                                   string $stateKey = "", int $timestamp = null) {
        $path = sprintf("/rooms/%s/state/%s", urlencode($roomId), urlencode($eventType));
        if ($stateKey) {
            $path .= sprintf("/%s", urlencode($stateKey));
        }
        $params = [];
        if ($timestamp) {
            $params["ts"] = $timestamp;
        }

        return $this->send('PUT', $path, $content, $params);
    }

    /**
     * Perform GET /rooms/$room_id/state/$event_type
     *
     * @param string $roomId The room ID.
     * @param string $eventType The type of the event.
     * @return array|string
     * @throws MatrixRequestException (code=404) if the state event is not found.
     * @throws MatrixException
     */
    public function getStateEvent(string $roomId, string $eventType) {
        $path = sprintf('/rooms/%s/state/%s', urlencode($roomId), urlencode($eventType));

        return $this->send('GET', $path);
    }

    /**
     * @param string $roomId The room ID to send the message event in.
     * @param string $eventType The event type to send.
     * @param array $content The JSON content to send.
     * @param int $txnId Optional. The transaction ID to use.
     * @param int $timestamp Set origin_server_ts (For application services only)
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function sendMessageEvent(string $roomId, string $eventType, array $content,
                                     int $txnId = null, int $timestamp = null) {
        if (!$txnId) {
            $txnId = $this->makeTxnId();
        }
        $path = sprintf('/rooms/%s/send/%s/%s', urlencode($roomId), urlencode($eventType), urlencode($txnId));
        $params = [];
        if ($timestamp) {
            $params['ts'] = $timestamp;
        }

        return $this->send('PUT', $path, $content, $params);
    }

    /**
     * Perform PUT /rooms/$room_id/redact/$event_id/$txn_id/
     *
     * @param string $roomId The room ID to redact the message event in.
     * @param string $eventId The event id to redact.
     * @param string $reason Optional. The reason the message was redacted.
     * @param int|null $txnId Optional. The transaction ID to use.
     * @param int|null $timestamp Optional. Set origin_server_ts (For application services only)
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function redactEvent(string $roomId, string $eventId, ?string $reason = null,
                                int $txnId = null, int $timestamp = null) {
        if (!$txnId) {
            $txnId = $this->makeTxnId();
        }
        $path = sprintf('/rooms/%s/redact/%s/%s', urlencode($roomId), urlencode($eventId), urlencode($txnId));
        $params = [];
        $content = [];
        if ($reason) {
            $content['reason'] = $reason;
        }
        if ($timestamp) {
            $params['ts'] = $timestamp;
        }

        return $this->send('PUT', $path, $content, $params);
    }

    /**
     * $content_type can be a image,audio or video
     * extra information should be supplied, see
     * https://matrix.org/docs/spec/r0.0.1/client_server.html
     *
     * @param string $roomId
     * @param string $itemUrl
     * @param string $itemName
     * @param string $msgType
     * @param array $extraInformation
     * @param int|null $timestamp
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function sendContent(string $roomId, string $itemUrl, string $itemName, string $msgType,
                                array $extraInformation = [], int $timestamp = null) {
        $contentPack = [
            "url" => $itemUrl,
            "msgtype" => $msgType,
            "body" => $itemName,
            "info" => $extraInformation,
        ];

        return $this->sendMessageEvent($roomId, 'm.room.message', $contentPack, null, $timestamp);
    }


    /**
     * Send m.location message event
     * http://matrix.org/docs/spec/client_server/r0.2.0.html#m-location
     *
     * @param string $roomId The room ID to send the event in.
     * @param string $geoUri The geo uri representing the location.
     * @param string $name Description for the location.
     * @param string|null $thumbUrl URL to the thumbnail of the location.
     * @param array|null $thumbInfo Metadata about the thumbnail, type ImageInfo.
     * @param int|null $timestamp Set origin_server_ts (For application services only)
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function sendLocation(string $roomId, string $geoUri, string $name, string $thumbUrl = null,
                                 array $thumbInfo = null, int $timestamp = null) {
        $contentPack = [
            "geo_uri" => $geoUri,
            "msgtype" => "m.location",
            "body" => $name,
        ];
        if ($thumbUrl) {
            $contentPack['thumbnail_url'] = $thumbUrl;
        }
        if ($thumbInfo) {
            $contentPack['thumbnail_info'] = $thumbInfo;
        }

        return $this->sendMessageEvent($roomId, 'm.room.message', $contentPack, null, $timestamp);
    }

    /**
     * Perform PUT /rooms/$room_id/send/m.room.message
     *
     * @param string $roomId The room ID to send the event in.
     * @param string $textContent The m.text body to send.
     * @param string $msgType
     * @param int|null $timestamp Set origin_server_ts (For application services only)
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function sendMessage(string $roomId, string $textContent, string $msgType = 'm.text', int $timestamp = null) {
        $textBody = $this->getTextBody($textContent, $msgType);

        return $this->sendMessageEvent($roomId, 'm.room.message', $textBody, null, $timestamp);
    }

    /**
     * Perform PUT /rooms/$room_id/send/m.room.message with m.emote msgtype
     *
     * @param string $roomId The room ID to send the event in.
     * @param string $textContent The m.emote body to send.
     * @param int|null $timestamp Set origin_server_ts (For application services only)
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function sendEmote(string $roomId, string $textContent, int $timestamp = null) {
        $body = $this->getEmoteBody($textContent);

        return $this->sendMessageEvent($roomId, 'm.room.message', $body, null, $timestamp);
    }

    /**
     * Perform PUT /rooms/$room_id/send/m.room.message with m.notice msgtype
     *
     * @param string $roomId The room ID to send the event in.
     * @param string $textContent The m.emote body to send.
     * @param int|null $timestamp Set origin_server_ts (For application services only)
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function sendNotice(string $roomId, string $textContent, int $timestamp = null) {
        $body = [
            'msgtype' => 'm.notice',
            'body' => $textContent,
        ];

        return $this->sendMessageEvent($roomId, 'm.room.message', $body, null, $timestamp);
    }

    /**
     * @param string $roomId The room's id.
     * @param string $token The token to start returning events from.
     * @param string $direction The direction to return events from. One of: ["b", "f"].
     * @param int $limit The maximum number of events to return.
     * @param string|null $to The token to stop returning events at.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getRoomMessages(string $roomId, string $token, string $direction, int $limit = 10, string $to = null) {
        $query = [
            "roomId" => $roomId,
            "from" => $token,
            'dir' => $direction,
            'limit' => $limit,
        ];

        if ($to) {
            $query['to'] = $to;
        }
        $path = sprintf('/rooms/%s/messages', urlencode($roomId));

        return $this->send('GET', $path, null, $query);
    }

    /**
     * Perform GET /rooms/$room_id/state/m.room.name
     *
     * @param string $roomId The room ID
     * @return array|string
     * @throws MatrixException
     * @throws MatrixRequestException
     */
    public function getRoomName(string $roomId) {
        return $this->getStateEvent($roomId, 'm.room.name');
    }

    /**
     * Perform PUT /rooms/$room_id/state/m.room.name
     *
     * @param string $roomId The room ID
     * @param string $name The new room name
     * @param int|null $timestamp Set origin_server_ts (For application services only)
     * @return array|string
     * @throws MatrixException
     */
    public function setRoomName(string $roomId, string $name, int $timestamp = null) {
        $body = ['name' => $name];

        return $this->sendStateEvent($roomId, 'm.room.name', $body, '', $timestamp);
    }

    /**
     * Perform GET /rooms/$room_id/state/m.room.topic
     *
     * @param string $roomId The room ID
     * @return array|string
     * @throws MatrixException
     * @throws MatrixRequestException
     */
    public function getRoomTopic(string $roomId) {
        return $this->getStateEvent($roomId, 'm.room.topic');
    }

    /**
     * Perform PUT /rooms/$room_id/state/m.room.topic
     *
     * @param string $roomId The room ID
     * @param string $topic The new room topic
     * @param int|null $timestamp Set origin_server_ts (For application services only)
     * @return array|string
     * @throws MatrixException
     */
    public function setRoomTopic(string $roomId, string $topic, int $timestamp = null) {
        $body = ['topic' => $topic];

        return $this->sendStateEvent($roomId, 'm.room.topic', $body, '', $timestamp);
    }


    /**
     * Perform GET /rooms/$room_id/state/m.room.power_levels
     *
     *
     * @param string $roomId The room ID
     * @return array|string
     * @throws MatrixException
     * @throws MatrixRequestException
     */
    public function getPowerLevels(string $roomId) {
        return $this->getStateEvent($roomId, 'm.room.power_levels');
    }

    /**
     * Perform PUT /rooms/$room_id/state/m.room.power_levels
     *
     * Note that any power levels which are not explicitly specified
     * in the content arg are reset to default values.
     *
     *
     * Example:
     *       $api = new MatrixHttpApi("http://example.com", $token="foobar")
     *              $api->setPowerLevels("!exampleroom:example.com",
     *                  [
     *                      "ban" => 50, # defaults to 50 if unspecified
     *                      "events": [
     *                          "m.room.name" => 100, # must have PL 100 to change room name
     *                          "m.room.power_levels" => 100 # must have PL 100 to change PLs
     *                      ],
     *                     "events_default" => 0, # defaults to 0
     *                      "invite" => 50, # defaults to 50
     *                      "kick" => 50, # defaults to 50
     *                      "redact" => 50, # defaults to 50
     *                      "state_default" => 50, # defaults to 50 if m.room.power_levels exists
     *                      "users" => [
     *                          "@someguy:example.com" => 100 # defaults to 0
     *                      ],
     *                      "users_default" => 0 # defaults to 0
     *                  ]
     *              );
     *
     * @param string $roomId
     * @param array $content
     * @return array|string
     * @throws MatrixException
     */
    public function setPowerLevels(string $roomId, array $content) {
        // Synapse returns M_UNKNOWN if body['events'] is omitted,
        //  as of 2016-10-31
        if (!array_key_exists('events', $content)) {
            $content['events'] = [];
        }

        return $this->sendStateEvent($roomId, 'm.room.power_levels', $content);
    }

    /**
     * Perform POST /rooms/$room_id/leave
     *
     * @param string $roomId The room ID
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function leaveRoom(string $roomId) {
        return $this->send('POST', sprintf('/rooms/%s/leave', urlencode($roomId)));
    }

    /**
     * Perform POST /rooms/$room_id/forget
     *
     * @param string $roomId The room ID
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function forgetRoom(string $roomId) {
        return $this->send('POST', sprintf('/rooms/%s/forget', urlencode($roomId)), []);
    }

    /**
     * Perform POST /rooms/$room_id/invite
     *
     * @param string $roomId The room ID
     * @param string $userId The user ID of the invitee
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function inviteUser(string $roomId, string $userId) {
        $body = ['user_id' => $userId];

        return $this->send('POST', sprintf('/rooms/%s/invite', urlencode($roomId)), $body);
    }

    /**
     * Calls set_membership with membership="leave" for the user_id provided
     *
     * @param string $roomId The room ID
     * @param string $userId The user ID
     * @param string $reason Optional. The reason for kicking them out
     * @return mixed
     * @throws MatrixException
     */
    public function kickUser(string $roomId, string $userId, string $reason = '') {
        return $this->setMembership($roomId, $userId, 'leave', $reason);
    }

    /**
     * Perform GET /rooms/$room_id/state/m.room.member/$user_id
     *
     * @param string $roomId The room ID
     * @param string $userId The user ID
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getMembership(string $roomId, string $userId) {
        $path = sprintf('/rooms/%s/state/m.room.member/%s', urlencode($roomId), urlencode($userId));

        return $this->send('GET', $path);
    }

    /**
     * Perform PUT /rooms/$room_id/state/m.room.member/$user_id
     *
     * @param string $roomId The room ID
     * @param string $userId The user ID
     * @param string $membership New membership value
     * @param string $reason The reason
     * @param array $profile
     * @param int|null $timestamp Set origin_server_ts (For application services only)
     * @return array|string
     * @throws MatrixException
     */
    public function setMembership(string $roomId, string $userId, string $membership, string $reason = '', array $profile = [], int $timestamp = null) {
        $body = [
            'membership' => $membership,
            'reason' => $reason,
        ];
        if (array_key_exists('displayname', $profile)) {
            $body['displayname'] = $profile['displayname'];
        }
        if (array_key_exists('avatar_url', $profile)) {
            $body['avatar_url'] = $profile['avatar_url'];
        }

        return $this->sendStateEvent($roomId, 'm.room.member', $body, $userId, $timestamp);
    }

    /**
     * Perform POST /rooms/$room_id/ban
     *
     * @param string $roomId The room ID
     * @param string $userId The user ID of the banee(sic)
     * @param string $reason The reason for this ban
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function banUser(string $roomId, string $userId, string $reason = '') {
        $body = [
            'user_id' => $userId,
            'reason' => $reason,
        ];

        return $this->send('POST', sprintf('/rooms/%s/ban', urlencode($roomId)), $body);
    }

    /**
     * Perform POST /rooms/$room_id/unban
     *
     * @param string $roomId The room ID
     * @param string $userId The user ID of the banee(sic)
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function unbanUser(string $roomId, string $userId) {
        $body = [
            'user_id' => $userId,
        ];

        return $this->send('POST', sprintf('/rooms/%s/unban', urlencode($roomId)), $body);
    }

    /**
     * @param string $userId
     * @param string $roomId
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getUserTags(string $userId, string $roomId) {
        $path = sprintf('/user/%s/rooms/%s/tags', urlencode($userId), urlencode($roomId));

        return $this->send('GET', $path);
    }

    /**
     * @param string $userId
     * @param string $roomId
     * @param string $tag
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function removeUserTag(string $userId, string $roomId, string $tag) {
        $path = sprintf('/user/%s/rooms/%s/tags/%s', urlencode($userId), urlencode($roomId), urlencode($tag));

        return $this->send('DELETE', $path);
    }

    /**
     * @param string $userId
     * @param string $roomId
     * @param string $tag
     * @param float|null $order
     * @param array $body
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function addUserTag(string $userId, string $roomId, string $tag, ?float $order = null, array $body = []) {
        if ($order) {
            $body['order'] = $order;
        }
        $path = sprintf('/user/%s/rooms/%s/tags/%s', urlencode($userId), urlencode($roomId), urlencode($tag));

        return $this->send('PUT', $path, $body);
    }

    /**
     * @param string $userId
     * @param string $type
     * @param array $accountData
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function setAccountData(string $userId, string $type, array $accountData) {
        $path = sprintf("/user/%s/account_data/%s", urlencode($userId), urlencode($type));

        return $this->send('PUT', $path, $accountData);
    }

    /**
     * @param string $userId
     * @param string $roomId
     * @param string $type
     * @param array $accountData
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function setRoomAccountData(string $userId, string $roomId, string $type, array $accountData) {
        $path = sprintf(
            '/user/%s/rooms/%s/account_data/%s',
            urlencode($userId), urlencode($roomId), urlencode($type)
        );

        return $this->send('PUT', $path, $accountData);
    }

    /**
     * Perform GET /rooms/$room_id/state
     *
     * @param string $roomId The room ID
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getRoomState(string $roomId) {
        return $this->send('GET', sprintf('/rooms/%s/state', urlencode($roomId)));
    }

    public function getTextBody(string $textContent, string $msgType = 'm.text'): array {
        return [
            'msgtype' => $msgType,
            'body' => $textContent,
        ];
    }

    private function getEmoteBody(string $textContent): array {
        return $this->getTextBody($textContent, 'm.emote');
    }

    /**
     * @param string $userId
     * @param string $filterId
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getFilter(string $userId, string $filterId) {
        $path = sprintf("/user/%s/filter/%s", urlencode($userId), urlencode($filterId));

        return $this->send('GET', $path);
    }

    /**
     * @param string $userId
     * @param array $filterParams
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function createFilter(string $userId, array $filterParams) {
        $path = sprintf("/user/%s/filter", urlencode($userId));

        return $this->send('POST', $path, $filterParams);
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
     * @throws MatrixRequestException
     * @throws MatrixHttpLibException
     */
    private function send(string $method, string $path, $content = null, array $queryParams = [], array $headers = [],
                          $apiPath = self::MATRIX_V2_API_PATH, $returnJson = true) {
        $options = [];
        if (!in_array('User-Agent', $headers)) {
            $headers['User-Agent'] = 'php-matrix-sdk/' . self::VERSION;
        }

        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'])) {
            throw new MatrixException("Unsupported HTTP method: $method");
        }

        if (!in_array('Content-Type', array_keys($headers))) {
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

        $options = array_merge($options, [
            RequestOptions::HEADERS => $headers,
            RequestOptions::QUERY => $queryParams,
            RequestOptions::VERIFY => $this->validateCert,
            RequestOptions::HTTP_ERRORS => FALSE,
        ]);

        $endpoint = $this->baseUrl . $apiPath . $path;
        if ($headers['Content-Type'] == "application/json" && $content !== null) {
            $options[RequestOptions::JSON] = $content;
        }
        else {
            $options[RequestOptions::BODY] = $content;
        }

        $responseBody = '';
        while (true) {
            try {
                $response = $this->client->request($method, $endpoint, $options);
            } catch (GuzzleException $e) {
                throw new MatrixHttpLibException($e, $method, $endpoint);
            }

            $responseBody = $response->getBody()->getContents();

            if ($response->getStatusCode() >= 500) {
                throw new MatrixUnexpectedResponse($responseBody);
            }

            if ($response->getStatusCode() != 429) {
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

    /**
     * @param $content
     * @param string $contentType
     * @param string|null $filename
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function mediaUpload($content, string $contentType, string $filename = null) {
        $headers = ['Content-Type' => $contentType];
        $apiPath = self::MATRIX_V2_MEDIA_PATH . "/upload";
        $queryParam = [];
        if ($filename) {
            $queryParam['filename'] = $filename;
        }

        return $this->send('POST', '', $content, $queryParam, $headers, $apiPath);
    }

    /**
     * @param string $userId
     * @return string|null
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getDisplayName(string $userId): ?string {
        $content = $this->send("GET", "/profile/$userId/displayname");

        return array_get($content, 'displayname');
    }

    /**
     * @param string $userId
     * @param string $displayName
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function setDisplayName(string $userId, string $displayName) {
        $content = ['displayname' => $displayName];
        $path = sprintf('/profile/%s/displayname', urlencode($userId));

        return $this->send('PUT', $path, $content);
    }

    /**
     * @param string $userId
     * @return mixed
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getAvatarUrl(string $userId): ?string {
        $content = $this->send("GET", "/profile/$userId/avatar_url");

        return array_get($content, 'avatar_url');
    }

    /**
     * @param string $userId
     * @param string $avatarUrl
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function setAvatarUrl(string $userId, string $avatarUrl) {
        $content = ['avatar_url' => $avatarUrl];
        $path = sprintf('/profile/%s/avatar_url', urlencode($userId));

        return $this->send('PUT', $path, $content);
    }

    /**
     * @param string $mxcurl
     * @return string
     * @throws ValidationException
     */
    public function getDownloadUrl(string $mxcurl): string {
        Util::checkMxcUrl($mxcurl);

        return $this->baseUrl . self::MATRIX_V2_MEDIA_PATH . "/download/" . substr($mxcurl, 6);
    }

    /**
     * Download raw media from provided mxc URL.
     *
     * @param string $mxcurl mxc media URL.
     * @param bool $allowRemote Indicates to the server that it should not
     *      attempt to fetch the media if it is deemed remote. Defaults
     *      to true if not provided.
     * @return string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     * @throws ValidationException
     */
    public function mediaDownload(string $mxcurl, bool $allowRemote = true) {
        Util::checkMxcUrl($mxcurl);
        $queryParam = [];
        if (!$allowRemote) {
            $queryParam["allow_remote"] = false;
        }
        $path = substr($mxcurl, 6);
        $apiPath = self::MATRIX_V2_MEDIA_PATH . "/download/";

        return $this->send('GET', $path, null, $queryParam, [], $apiPath, false);
    }

    /**
     * Download raw media thumbnail from provided mxc URL.
     *
     * @param string $mxcurl mxc media URL
     * @param int $width desired thumbnail width
     * @param int $height desired thumbnail height
     * @param string $method thumb creation method. Must be
     *      in ['scale', 'crop']. Default 'scale'.
     * @param bool $allowRemote indicates to the server that it should not
     *      attempt to fetch the media if it is deemed remote. Defaults
     *      to true if not provided.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     * @throws ValidationException
     */
    public function getThumbnail(string $mxcurl, int $width, int $height,
                                 string $method = 'scale', bool $allowRemote = true) {
        Util::checkMxcUrl($mxcurl);
        if (!in_array($method, ['scale', 'crop'])) {
            throw new ValidationException('Unsupported thumb method ' . $method);
        }
        $queryParams = [
            "width" => $width,
            "height" => $height,
            "method" => $method,
        ];
        if (!$allowRemote) {
            $queryParams["allow_remote"] = false;
        }
        $path = substr($mxcurl, 6);
        $apiPath = self::MATRIX_V2_MEDIA_PATH . "/thumbnail/";


        return $this->send('GET', $path, null, $queryParams, [], $apiPath, false);
    }

    /**
     * Get preview for URL.
     *
     * @param string $url URL to get a preview
     * @param float|null $ts The preferred point in time to return
     *      a preview for. The server may return a newer
     *      version if it does not have the requested
     *      version available.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getUrlPreview(string $url, float $ts = null) {
        $params = ['url' => $url];
        if ($ts) {
            $params['ts'] = $ts;
        }
        $apiPath = self::MATRIX_V2_MEDIA_PATH . '/preview_url';

        return $this->send('GET', '', null, $params, [], $apiPath);
    }

    /**
     * Get room id from its alias.
     *
     * @param string $roomAlias The room alias name.
     * @return null|string Wanted room's id.
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getRoomId(string $roomAlias): ?string {
        $content = $this->send('GET', sprintf("/directory/room/%s", urlencode($roomAlias)));

        return array_get($content, 'room_id');
    }

    /**
     * Set alias to room id
     *
     * @param string $roomId The room id.
     * @param string $roomAlias The room wanted alias name.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function setRoomAlias(string $roomId, string $roomAlias) {
        $content = ['room_id' => $roomId];

        return $this->send('PUT', sprintf("/directory/room/%s", urlencode($roomAlias)), $content);
    }

    /**
     * Remove mapping of an alias
     *
     * @param string $roomAlias The alias to be removed.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function removeRoomAlias(string $roomAlias) {
        return $this->send('DELETE', sprintf("/directory/room/%s", urlencode($roomAlias)));
    }

    /**
     * Get the list of members for this room.
     *
     * @param string $roomId The room to get the member events for.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getRoomMembers(string $roomId) {
        return $this->send('GET', sprintf("/rooms/%s/members", urlencode($roomId)));
    }

    /**
     * Set the rule for users wishing to join the room.
     *
     * @param string $roomId The room to set the rules for.
     * @param string $joinRule The chosen rule. One of: ["public", "knock", "invite", "private"]
     * @return array|string
     * @throws MatrixException
     */
    public function setJoinRule(string $roomId, string $joinRule) {
        $content = ['join_rule' => $joinRule];

        return $this->sendStateEvent($roomId, 'm.room.join_rule', $content);
    }

    /**
     * Set the guest access policy of the room.
     *
     * @param string $roomId The room to set the rules for.
     * @param string $guestAccess Wether guests can join. One of: ["can_join", "forbidden"]
     * @return array|string
     * @throws MatrixException
     */
    public function setGuestAccess(string $roomId, string $guestAccess) {
        $content = ['guest_access' => $guestAccess];

        return $this->sendStateEvent($roomId, 'm.room.guest_access', $content);
    }

    /**
     * Gets information about all devices for the current user.
     *
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getDevices() {
        return $this->send('GET', '/devices');
    }

    /**
     * Gets information on a single device, by device id.
     *
     * @param string $deviceId
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function getDevice(string $deviceId) {
        return $this->send('GET', sprintf('/devices/%s', urlencode($deviceId)));
    }

    /**
     * Update the display name of a device.
     *
     * @param string $deviceId The device ID of the device to update.
     * @param string $displayName New display name for the device.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function updateDeviceInfo(string $deviceId, string $displayName) {
        $content = ['display_name' => $displayName];

        return $this->send('PUT', sprintf('/devices/%s', urlencode($deviceId)), $content);
    }

    /**
     * Deletes the given device, and invalidates any access token associated with it.
     *
     * NOTE: This endpoint uses the User-Interactive Authentication API.
     *
     * @param array $authBody Authentication params.
     * @param string $deviceId The device ID of the device to delete.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function deleteDevice(array $authBody, string $deviceId) {
        $content = ['auth' => $authBody];

        return $this->send('DELETE', sprintf('/devices/%s', urlencode($deviceId)), $content);
    }

    /**
     * Bulk deletion of devices.
     *
     * NOTE: This endpoint uses the User-Interactive Authentication API.
     *
     * @param array $authBody Authentication params.
     * @param array $devices List of device ID"s to delete.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function deleteDevices($authBody, $devices) {
        $content = [
            'auth' => $authBody,
            'devices' => $devices
        ];

        return $this->send('POST', '/delete_devices', $content);
    }

    /**
     * Publishes end-to-end encryption keys for the device.
     * Said device must be the one used when logging in.
     *
     * @param array $deviceKeys Optional. Identity keys for the device. The required keys are:
     *      | user_id (str): The ID of the user the device belongs to. Must match the user ID used when logging in.
     *      | device_id (str): The ID of the device these keys belong to. Must match the device ID used when logging in.
     *      | algorithms (list<str>): The encryption algorithms supported by this device.
     *      | keys (dict): Public identity keys. Should be formatted as <algorithm:device_id>: <key>.
     *      | signatures (dict): Signatures for the device key object. Should be formatted as <user_id>: {<algorithm:device_id>: <key>}
     * @param array $oneTimeKeys Optional. One-time public keys. Should be
     *      formatted as <algorithm:key_id>: <key>, the key format being
     *      determined by the algorithm.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function uploadKeys(array $deviceKeys = [], array $oneTimeKeys = []) {
        $content = [];
        if ($deviceKeys) {
            $content['device_keys'] = $deviceKeys;
        }
        if ($oneTimeKeys) {
            $content['one_time_keys'] = $oneTimeKeys;
        }

        return $this->send('POST', '/keys/upload', $content ?: null);
    }

    /**
     * Query HS for public keys by user and optionally device.
     *
     * @param array $userDevices The devices whose keys to download. Should be
     *      formatted as <user_id>: [<device_ids>]. No device_ids indicates
     *      all devices for the corresponding user.
     * @param int $timeout Optional. The time (in milliseconds) to wait when
     *      downloading keys from remote servers.
     * @param string $token Optional. If the client is fetching keys as a result of
     *      a device update received in a sync request, this should be the
     *      'since' token of that sync request, or any later sync token.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function queryKeys(array $userDevices, int $timeout = null, string $token = null) {
        $content = ['device_keys' => $userDevices];
        if ($timeout) {
            $content['timeout'] = $timeout;
        }
        if ($token) {
            $content['token'] = $token;
        }

        return $this->send('POST', "/keys/query", $content);
    }

    /**
     * Claims one-time keys for use in pre-key messages.
     *
     * @param array $keyRequest The keys to be claimed. Format should be <user_id>: { <device_id>: <algorithm> }.
     * @param int $timeout Optional. The time (in ms) to wait when downloading keys from remote servers.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function claimKeys(array $keyRequest, int $timeout) {
        $content = ['one_time_keys' => $keyRequest];
        if ($timeout) {
            $content['timeout'] = $timeout;
        }

        return $this->send('POST', "/keys/claim", $content);
    }

    /**
     * Gets a list of users who have updated their device identity keys.
     *
     * @param string $fromToken The desired start point of the list. Should be the
     *      next_batch field from a response to an earlier call to /sync.
     * @param string $toToken The desired end point of the list. Should be the next_batch
     *      field from a recent call to /sync - typically the most recent such call.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function keyChanges(string $fromToken, string $toToken) {
        $params = [
            'from' => $fromToken,
            'to' => $toToken,
        ];

        return $this->send("GET", "/keys/changes", null, $params);
    }

    /**
     * Sends send-to-device events to a set of client devices.
     *
     * @param string $eventType The type of event to send.
     * @param array $messages The messages to send. Format should be
     *      <user_id>: {<device_id>: <event_content>}.
     *      The device ID may also be '*', meaning all known devices for the user.
     * @param string|null $txnId Optional. The transaction ID for this event, will be generated automatically otherwise.
     * @return array|string
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function sendToDevice(string $eventType, array $messages, string $txnId = null) {
        $txnId = $txnId ?: $this->makeTxnId();
        $content = ['messages' => $messages];
        $path = sprintf("/sendToDevice/%s/%s", urlencode($eventType), urlencode($txnId));

        return $this->send('PUT', $path, $content);
    }

    private function makeTxnId(): int {
        $txnId = $this->txnId . (int)(microtime(true) * 1000);
        $this->txnId++;

        return $txnId;
    }

    /**
     * Determine user_id for authenticated user.
     *
     * @return array
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function whoami(): array {
        if (!$this->token) {
            throw new MatrixException('Authentication required.');
        }

        return $this->send('GET', '/account/whoami');
    }

    public function setToken(?string $token) {
        $this->token = $token;
    }


}
