<?php

namespace Aryess\PhpMatrixSdk;

use Aryess\PhpMatrixSdk\Crypto\OlmDevice;
use Aryess\PhpMatrixSdk\Exceptions\MatrixRequestException;
use Aryess\PhpMatrixSdk\Exceptions\MatrixUnexpectedResponse;
use Aryess\PhpMatrixSdk\Exceptions\ValidationException;
use phpDocumentor\Reflection\Types\Callable_;

//TODO: port OLM bindings
define('ENCRYPTION_SUPPORT', false);

/**
 * The client API for Matrix. For the raw HTTP calls, see MatrixHttpApi.
 *
 * Examples:
 *
 *    Create a new user and send a message::
 *
 *    $client = new MatrixClient("https://matrix.org");
 *    $token = $client->registerWithPassword($username="foobar", $password="monkey");
 *    $room = $client->createRoom("myroom");
 *    $room->sendImage($fileLikeObject);
 *
 *    Send a message with an already logged in user::
 *
 *    $client = new MatrixClient("https://matrix.org", $token="foobar", $userId="@foobar:matrix.org");
 *    $client->addListener(func);  // NB: event stream callback
 *    $client->rooms[0]->addListener(func);  // NB: callbacks just for this room.
 *    $room = $client->joinRoom("#matrix:matrix.org");
 *    $response = $room->sendText("Hello!");
 *    $response = $room->kick("@bob:matrix.org");
 *
 *    Incoming event callbacks (scopes)::
 *
 *    function userCallback($user, $incomingEvent);
 *
 *    function $roomCallback($room, $incomingEvent);
 *
 *    function globalCallback($incoming_event);
 *
 * @package Aryess\PhpMatrixSdk
 */
class MatrixClient {


    /**
     * @var int
     */
    protected $cacheLevel;

    /**
     * @var bool
     */
    protected $encryption;

    /**
     * @var null
     */
    protected $encryptionConf;

    /**
     * @var MatrixHttpApi
     */
    protected $api;
    /**
     * @var array
     */
    protected $listeners = [];
    protected $presenceListeners = [];
    protected $inviteListeners = [];
    protected $leftListeners = [];
    protected $ephemeralListeners = [];
    protected $deviceId;
    /**
     * @var OlmDevice
     */
    protected $olmDevice;
    protected $syncToken;
    protected $syncFilter;
    protected $syncThread;
    protected $shouldListen = false;
    /**
     * @var int Time to wait before attempting a /sync request after failing.
     */
    protected $badSyncTimeoutLimit = 3600;
    protected $rooms = [];
    /**
     * @var array A map from user ID to `User` object.
     *          It is populated automatically while tracking the membership in rooms, and
     *          shouldn't be modified directly.
     *          A `User` object in this array is shared between all `Room`
     *          objects where the corresponding user is joined.
     */
    public $users = [];
    protected $userId;
    protected $token;
    protected $hs;

    /**
     * MatrixClient constructor.
     * @param string $baseUrl The url of the HS preceding /_matrix. e.g. (ex: https://localhost:8008 )
     * @param string|null $token If you have an access token supply it here.
     * @param bool $validCertCheck Check the homeservers certificate on connections?
     * @param int $syncFilterLimit
     * @param int $cacheLevel One of Cache::NONE, Cache::SOME, or Cache::ALL
     * @param bool $encryption Optional. Whether or not to enable end-to-end encryption support
     * @param array $encryptionConf Optional. Configuration parameters for encryption.
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     * @throws ValidationException
     */
    public function __construct(string $baseUrl, ?string $token = null, bool $validCertCheck = true, int $syncFilterLimit = 20,
                                int $cacheLevel = Cache::ALL, $encryption = false, $encryptionConf = []) {
        if ($encryption && ENCRYPTION_SUPPORT) {
            throw new ValidationException('Failed to enable encryption. Please make sure the olm library is available.');
        }

        $this->api = new MatrixHttpApi($baseUrl, $token);
        $this->api->validateCertificate($validCertCheck);
        $this->encryption = $encryption;
        $this->encryptionConf = $encryptionConf;
        if (!in_array($cacheLevel, Cache::$levels)) {
            throw new ValidationException('$cacheLevel must be one of Cache::NONE, Cache::SOME, Cache::ALL');
        }
        $this->cacheLevel = $cacheLevel;
        $this->syncFilter = sprintf('{ "room": { "timeline" : { "limit" : %d } } }', $syncFilterLimit);
        if ($token) {
            $response = $this->api->whoami();
            $this->userId = $response['user_id'];
            $this->sync();
        }
    }

    /**
     * Register a guest account on this HS.
     *
     * Note: HS must have guest registration enabled.
     *
     * @return string|null Access Token
     * @throws Exceptions\MatrixException
     */
    public function registerAsGuest(): ?string {
        $response = $this->api->register([], 'guest');

        return $this->postRegistration($response);
    }

    /**
     * Register for a new account on this HS.
     *
     * @param string $username Account username
     * @param string $password Account password
     * @return string|null Access Token
     * @throws Exceptions\MatrixException
     */
    public function registerWithPassword(string $username, string $password): ?string {
        $auth = ['type' => 'm.login.dummy'];
        $response = $this->api->register($auth, 'user', false, $username, $password);

        return $this->postRegistration($response);
    }

    protected function postRegistration(array $response) {
        $this->userId = array_get($response, 'user_id');
        $this->token = array_get($response, 'access_token');
        $this->hs = array_get($response, 'home_server');
        $this->api->setToken($this->token);
        $this->sync();

        return $this->token;
    }

    public function login(string $username, string $password, bool $sync = true,
                          int $limit = 10, ?string $deviceId = null): ?string {
        $response = $this->api->login('m.login.password', [
            'identifier' => [
                'type' => 'm.id.user',
                'user' => $username,
            ],
            'user' => $username,
            'password' => $password,
            'device_id' => $deviceId
        ]);

        $this->userId = array_get($response, 'user_id');
        $this->token = array_get($response, 'access_token');
        $this->hs = array_get($response, 'home_server');
        $this->api->setToken($this->token);
        $this->deviceId = array_get($response, 'device_id');

        if ($this->encryption) {
            $this->olmDevice = new OlmDevice($this->api, $this->userId, $this->deviceId, $this->encryptionConf);
            $this->olmDevice->uploadIdentityKeys();
            $this->olmDevice->uploadOneTimeKeys();
        }

        if ($sync) {
            $this->syncFilter = sprintf('{ "room": { "timeline" : { "limit" : %d } } }', $limit);
            $this->sync();
        }

        return $this->token;
    }

    /**
     * Logout from the homeserver.
     *
     * @throws Exceptions\MatrixException
     */
    public function logout() {
        $this->stopListenerThread();
        $this->api->logout();
    }

    /**
     * Create a new room on the homeserver.
     * TODO: move room creation/joining to User class for future application service usage
     * NOTE: we may want to leave thin wrappers here for convenience
     *
     * @param string|null $alias The canonical_alias of the room.
     * @param bool $isPublic The public/private visibility of the room.
     * @param array $invitees A set of user ids to invite into the room.
     * @return Room
     * @throws Exceptions\MatrixException
     */
    public function createRoom(?string $alias = null, bool $isPublic = false, array $invitees = []): Room {
        $response = $this->api->createRoom($alias, null, $isPublic, $invitees);

        return $this->mkRoom($response['room_id']);
    }

    /**
     * Join a room.
     *
     * @param string $roomIdOrAlias Room ID or an alias.
     * @return Room
     * @throws Exceptions\MatrixException
     */
    public function joinRoom(string $roomIdOrAlias): Room {
        $response = $this->api->joinRoom($roomIdOrAlias);
        $roomId = array_get($response, 'room_id', $roomIdOrAlias);

        return $this->mkRoom($roomId);
    }

    public function getRooms(): array {
        return $this->rooms;
    }

    /**
     * Add a listener that will send a callback when the client recieves an event.
     *
     * @param callable $callback Callback called when an event arrives.
     * @param string $eventType The event_type to filter for.
     * @return string Unique id of the listener, can be used to identify the listener.
     */
    public function addListener(callable $callback, string $eventType) {
        $listenerId = uniqid();
        $this->listeners[] = [
            'uid' => $listenerId,
            'callback' => $callback,
            'event_type' => $eventType,
        ];

        return $listenerId;
    }

    /**
     * Remove listener with given uid.
     *
     * @param string $uid Unique id of the listener to remove.
     */
    public function removeListener(string $uid) {
        $this->listeners = array_filter($this->listeners, function (array $a) use ($uid) {
            return $a['uid'] != $uid;
        });
    }

    /**
     * Add a presence listener that will send a callback when the client receives a presence update.
     *
     * @param callable $callback Callback called when a presence update arrives.
     * @return string Unique id of the listener, can be used to identify the listener.
     */
    public function addPresenceListener(callable $callback) {
        $listenerId = uniqid();
        $this->presenceListeners[$listenerId] = $callback;

        return $listenerId;
    }

    /**
     * Remove presence listener with given uid
     *
     * @param string $uid Unique id of the listener to remove
     */
    public function removePresenceListener(string $uid) {
        unset($this->presenceListeners[$uid]);
    }

    /**
     * Add an ephemeral listener that will send a callback when the client recieves an ephemeral event.
     *
     * @param callable $callback Callback called when an ephemeral event arrives.
     * @param string|null $eventType Optional. The event_type to filter for.
     * @return string Unique id of the listener, can be used to identify the listener.
     */
    public function addEphemeralListener(callable $callback, ?string $eventType = null) {
        $listenerId = uniqid();
        $this->ephemeralListeners[] = [
            'uid' => $listenerId,
            'callback' => $callback,
            'event_type' => $eventType,
        ];

        return $listenerId;
    }

    /**
     * Remove ephemeral listener with given uid.
     *
     * @param string $uid Unique id of the listener to remove.
     */
    public function removeEphemeralListener(string $uid) {
        $this->ephemeralListeners = array_filter($this->ephemeralListeners, function (array $a) use ($uid) {
            return $a['uid'] != $uid;
        });
    }

    /**
     * Add a listener that will send a callback when the client receives an invite.
     * @param callable $callback Callback called when an invite arrives.
     */
    public function addInviteListener(callable $callback) {
        $this->inviteListeners[] = $callback;
    }

    /**
     * Add a listener that will send a callback when the client has left a room.
     *
     * @param callable $callback Callback called when the client has left a room.
     */
    public function addLeaveListener(callable $callback) {
        $this->leftListeners[] = $callback;
    }

    public function listenForever(int $timeoutMs = 30000, ?callable $exceptionHandler = null, int $badSyncTimeout = 5) {
        $tempBadSyncTimeout = $badSyncTimeout;
        $this->shouldListen = true;
        while ($this->shouldListen) {
            try {
                $this->sync($timeoutMs);
                $tempBadSyncTimeout = $badSyncTimeout;
            } catch (MatrixRequestException $e) {
                // TODO: log error
                if ($e->getHttpCode() >= 500) {
                    sleep($badSyncTimeout);
                    $tempBadSyncTimeout = min($tempBadSyncTimeout * 2, $this->badSyncTimeoutLimit);
                } elseif (is_callable($exceptionHandler)) {
                    $exceptionHandler($e);
                } else {
                    throw $e;
                }
            } catch (Exception $e) {
                if (is_callable($exceptionHandler)) {
                    $exceptionHandler($e);
                } else {
                    throw $e;
                }
            }
            // TODO: we should also handle MatrixHttpLibException for retry in case no response
        }
    }

    public function startListenerThread(int $timeoutMs = 30000, ?callable $exceptionHandler = null) {
        // Just no
    }

    public function stopListenerThread() {
        if ($this->syncThread) {
            $this->shouldListen = false;
        }
    }

    /**
     * Upload content to the home server and recieve a MXC url.
     * TODO: move to User class. Consider creating lightweight Media class.
     *
     * @param mixed $content The data of the content.
     * @param string $contentType The mimetype of the content.
     * @param string|null $filename Optional. Filename of the content.
     * @return mixed
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws MatrixRequestException If the upload failed for some reason.
     * @throws MatrixUnexpectedResponse If the homeserver gave a strange response
     */
    public function upload($content, string $contentType, ?string $filename = null) {
        try {
            $response = $this->api->mediaUpload($content, $contentType, $filename);
            if (array_key_exists('content_uri', $response)) {
                return $response['content_uri'];
            }

            throw new MatrixUnexpectedResponse('The upload was successful, but content_uri wasn\'t found.');
        } catch (MatrixRequestException $e) {
            throw new MatrixRequestException($e->getHttpCode(), 'Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * @param string $roomId
     * @return Room
     * @throws Exceptions\MatrixException
     * @throws MatrixRequestException
     */
    private function mkRoom(string $roomId): Room {
        $room = new Room($this, $roomId);
        if ($this->encryption) {
            try {
                $event = $this->api->getStateEvent($roomId, "m.room.encryption");
                if ($event['algorithm'] === "m.megolm.v1.aes-sha2") {
                    $room->enableEncryption();
                }
            } catch (MatrixRequestException $e) {
                if ($e->getHttpCode() != 404) {
                    throw $e;
                }
            }
        }
        $this->rooms[$roomId] = $room;

        return $room;
    }

    /**
     * TODO better handling of the blocking I/O caused by update_one_time_key_counts
     *
     * @param int $timeoutMs
     * @throws Exceptions\MatrixException
     * @throws MatrixRequestException
     */
    public function sync(int $timeoutMs = 30000) {
        $response = $this->api->sync($this->syncToken, $timeoutMs, $this->syncFilter);
        $this->syncToken = $response['next_batch'];

        foreach (array_get($response, 'presence.events', []) as $presenceUpdate) {
            foreach ($this->presenceListeners as $cb) {
                $cb($presenceUpdate);
            }
        }
        foreach (array_get($response, 'rooms.invite', []) as $roomId => $inviteRoom) {
            foreach ($this->inviteListeners as $cb) {
                $cb($roomId, $inviteRoom['invite_state']);
            }
        }
        foreach (array_get($response, 'rooms.leave', []) as $roomId => $leftRoom) {
            foreach ($this->leftListeners as $cb) {
                $cb($roomId, $leftRoom);
            }
            if (array_key_exists($roomId, $this->rooms)) {
                unset($this->rooms[$roomId]);
            }
        }
        if ($this->encryption && array_key_exists('device_one_time_keys_count', $response)) {
            $this->olmDevice->updateOneTimeKeysCounts($response['device_one_time_keys_count']);
        }
        foreach (array_get($response, 'rooms.join', []) as $roomId => $syncRoom) {
            foreach ($this->inviteListeners as $cb) {
                $cb($roomId, $inviteRoom['invite_state']);
            }
            if (!array_key_exists($roomId, $this->rooms)) {
                $this->mkRoom($roomId);
            }
            $room = $this->rooms[$roomId];
            // TODO: the rest of this for loop should be in room object method
            $room->prevBatch = $syncRoom["timeline"]["prev_batch"];
            foreach (array_get($syncRoom, "state.events", []) as $event) {
                $event['room_id'] = $roomId;
                $room->processStateEvent($event);
            }
            foreach (array_get($syncRoom, "timeline.events", []) as $event) {
                $event['room_id'] = $roomId;
                $room->putEvent($event);

                // TODO: global listeners can still exist but work by each
                // $room.listeners[$uuid] having reference to global listener

                // Dispatch for client (global) listeners
                foreach ($this->listeners as $listener) {
                    if ($listener['event_type'] == null || $listener['event_type'] == $event['type']) {
                        $listener['callback']($event);
                    }
                }
            }
            foreach (array_get($syncRoom, "ephemeral.events", []) as $event) {
                $event['room_id'] = $roomId;
                $room->putEphemeralEvent($event);

                // Dispatch for client (global) listeners
                foreach ($this->ephemeralListeners as $listener) {
                    if ($listener['event_type'] == null || $listener['event_type'] == $event['type']) {
                        $listener['callback']($event);
                    }
                }
            }
        }
    }

    /**
     * Remove mapping of an alias
     *
     * @param string $roomAlias The alias to be removed.
     * @return bool True if the alias is removed, false otherwise.
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     */
    public function removeRoomAlias(string $roomAlias): bool {
        try {
            $this->api->removeRoomAlias($roomAlias);
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    public function api(): MatrixHttpApi {
        return $this->api;
    }

    public function userId():?string {
        return $this->userId;
    }

    public function cacheLevel() {
        return $this->cacheLevel;
    }

}