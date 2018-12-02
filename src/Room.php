<?php

namespace Aryess\PhpMatrixSdk;

use Aryess\PhpMatrixSdk\Exceptions\MatrixRequestException;
use function GuzzleHttp\default_ca_bundle;
use http\Exception;
use phpDocumentor\Reflection\DocBlock\Tags\Param;

/**
 * Call room-specific functions after joining a room from the client.
 *
 *  NOTE: This should ideally be called from within the Client.
 *  NOTE: This does not verify the room with the Home Server.
 *
 * @package Aryess\PhpMatrixSdk
 */
class Room {

    /** @var MatrixClient */
    protected $client;
    protected $roomId;
    protected $listeners = [];
    protected $stateListeners = [];
    protected $ephemeralListeners = [];
    protected $events = [];
    protected $eventHistoryLimit = 20;
    protected $name;
    protected $canonicalAlias;
    protected $aliases = [];
    protected $topic;
    protected $inviteOnly = false;
    protected $guestAccess;
    public $prevBatch;
    protected $_members = [];
    protected $membersDisplaynames = [
        // $userId: $displayname,
    ];
    protected $encrypted = false;

    public function __construct(MatrixClient $client, string $roomId) {
        Util::checkRoomId($roomId);
        $this->roomId = $roomId;
        $this->client = $client;
    }

    /**
     * Set user profile within a room.
     *
     * This sets displayname and avatar_url for the logged in user only in a
     * specific room. It does not change the user's global user profile.
     *
     * @param string|null $displayname
     * @param string|null $avatarUrl
     * @param string $reason
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function setUserProfile(?string $displayname = null, ?string $avatarUrl = null,
                                   string $reason = "Changing room profile information") {
        $member = $this->api()->getMembership($this->roomId, $this->client->userId());
        if ($member['membership'] != 'join') {
            throw new \Exception("Can't set profile if you have not joined the room.");
        }
        if (!$displayname) {
            $displayname = $member["displayname"];
        }
        if (!$avatarUrl) {
            $avatarUrl = $member["avatar_url"];
        }
        $this->api()->setMembership(
            $this->roomId,
            $this->client->userId(),
            'join',
            $reason,
            [
                "displayname" => $displayname,
                "avatar_url" => $avatarUrl
            ]
        );
    }

    /**
     * Calculates the display name for a room.
     *
     * @return string
     */
    public function displayName() {
        if ($this->name) {
            return $this->name;
        } elseif ($this->canonicalAlias) {
            return $this->canonicalAlias;
        }

        // Member display names without me
        $members = array_reduce($this->getJoinedMembers(), function (array $all, User $u) {
            if ($this->client->userId() != $u->userId()) {
                $all[] = $u->getDisplayName($this);
            }
            return $all;
        }, []);
        sort($members);

        switch (count($members)) {
            case 0:
                return 'Empty room';
            case 1:
                return $members[0];
            case 2:
                return sprintf("%s and %s", $members[0], $members[1]);
            default:
                return sprintf("%s and %d others.", $members[0], count($members));
        }
    }

    /**
     * Send a plain text message to the room.
     *
     * @param string $text
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function sendText(string $text) {
        return $this->api()->sendMessageEvent($this->roomId, $text);
    }

    public function getHtmlContent(string $html, ?string $body = null, string $msgType = 'm.text') {
        return [
            'body' => $body ?: strip_tags($html),
            'msgtype' => $msgType,
            'format' => "org.matrix.custom.html",
            'formatted_body' => $html,
        ];
    }

    /**
     * Send an html formatted message.
     *
     * @param string $html The html formatted message to be sent.
     * @param string|null $body The unformatted body of the message to be sent.
     * @param string $msgType
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function sendHtml(string $html, ?string $body = null, string $msgType = 'm.text') {
        $content = $this->getHtmlContent($html, $body, $msgType);

        return $this->api()->sendMessageEvent($this->roomId, 'm.room.message', $content);
    }

    /**
     * @param string $type
     * @param array $data
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function setAccountData(string $type, array $data) {
        return $this->api()->setRoomAccountData($this->client->userId(), $this->roomId, $type, $data);
    }

    /**
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function getTags() {
        return $this->api()->getUserTags($this->client->userId(), $this->roomId);
    }

    /**
     * @param string $tag
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function removeTag(string $tag) {
        return $this->api()->removeUserTag($this->client->userId(), $this->roomId, $tag);
    }

    /**
     * @param string $tag
     * @param float|null $order
     * @param array $content
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function addTag(string $tag, ?float $order = null, array $content = []) {
        return $this->api()->addUserTag($this->client->userId(), $this->roomId, $tag, $order, $content);
    }

    /**
     * Send an emote (/me style) message to the room.
     *
     * @param string $text
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function sendEmote(string $text) {
        return $this->api()->sendEmote($this->roomId, $text);
    }

    /**
     * Send a pre-uploaded file to the room.
     *
     * See http://matrix.org/docs/spec/r0.4.0/client_server.html#m-file for fileinfo.
     *
     * @param string $url The mxc url of the file.
     * @param string $name The filename of the image.
     * @param array $fileinfo Extra information about the file
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function sendFile(string $url, string $name, array $fileinfo) {
        return $this->api()->sendContent($this->roomId, $url, $name, 'm.file', $fileinfo);
    }

    /**
     * Send a notice (from bot) message to the room.
     *
     * @param string $text
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function sendNotice(string $text) {
        return $this->api()->sendNotice($this->roomId, $text);
    }

    /**
     * Send a pre-uploaded image to the room.
     *
     * See http://matrix.org/docs/spec/r0.0.1/client_server.html#m-image for imageinfo
     *
     * @param string $url The mxc url of the image.
     * @param string $name The filename of the image.
     * @param array $fileinfo Extra information about the image.
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function sendImage(string $url, string $name, ?array $fileinfo) {
        return $this->api()->sendContent($this->roomId, $url, $name, 'm.image', $fileinfo);
    }

    /**
     * Send a location to the room.
     * See http://matrix.org/docs/spec/client_server/r0.2.0.html#m-location for thumb_info
     *
     * @param string $geoUri The geo uri representing the location.
     * @param string $name Description for the location.
     * @param string|null $thumbUrl URL to the thumbnail of the location.
     * @param array $thumbInfo Metadata about the thumbnail, type ImageInfo.
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function sendLocation(string $geoUri, string $name, ?string $thumbUrl = null, ?array $thumbInfo) {
        return $this->api()->sendLocation($this->roomId, $geoUri, $name, $thumbUrl, $thumbInfo);
    }

    /**
     * Send a pre-uploaded video to the room.
     * See http://matrix.org/docs/spec/client_server/r0.2.0.html#m-video for videoinfo
     *
     * @param string $url The mxc url of the video.
     * @param string $name The filename of the video.
     * @param array $videoinfo Extra information about the video.
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function sendVideo(string $url, string $name, ?array $videoinfo) {
        return $this->api()->sendContent($this->roomId, $url, $name, 'm.video', $videoinfo);
    }

    /**
     * Send a pre-uploaded audio to the room.
     * See http://matrix.org/docs/spec/client_server/r0.2.0.html#m-audio for audioinfo
     *
     * @param string $url The mxc url of the video.
     * @param string $name The filename of the video.
     * @param array $audioinfo Extra information about the video.
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function sendAudio(string $url, string $name, ?array $audioinfo) {
        return $this->api()->sendContent($this->roomId, $url, $name, 'm.audio', $audioinfo);
    }

    /**
     * Redacts the message with specified event_id for the given reason.
     *
     * See https://matrix.org/docs/spec/r0.0.1/client_server.html#id112
     *
     * @param string $eventId
     * @param string|null $reason
     * @return array|string
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws Exceptions\MatrixRequestException
     */
    public function redactMessage(string $eventId, ?string $reason = null) {
        return $this->api()->redactEvent($this->roomId, $eventId, $reason);
    }

    /**
     * Add a callback handler for events going to this room.
     *
     * @param callable $cb (func(room, event)): Callback called when an event arrives.
     * @param string|null $eventType The event_type to filter for.
     * @return string Unique id of the listener, can be used to identify the listener.
     */
    public function addListener(callable $cb, ?string $eventType = null) {
        $listenerId = uniqid();
        $this->listeners[] = [
            'uid' => $listenerId,
            'callback' => $cb,
            'event_type' => $eventType,
        ];

        return $listenerId;
    }

    /**
     * Remove listener with given uid.
     *
     * @param string $uid
     */
    public function removeListener(string $uid) {
        $this->listeners = array_filter($this->listeners, function ($l) use ($uid) {
            return $l['uid'] != $uid;
        });
    }

    /**
     * Add a callback handler for ephemeral events going to this room.
     *
     * @param callable $cb (func(room, event)): Callback called when an ephemeral event arrives.
     * @param string|null $eventType The event_type to filter for.
     * @return string Unique id of the listener, can be used to identify the listener.
     */
    public function addEphemeralListener(callable $cb, ?string $eventType = null) {
        $listenerId = uniqid();
        $this->ephemeralListeners[] = [
            'uid' => $listenerId,
            'callback' => $cb,
            'event_type' => $eventType,
        ];

        return $listenerId;
    }

    /**
     * Remove ephemeral listener with given uid.
     *
     * @param string $uid
     */
    public function removeEphemeralListener(string $uid) {
        $this->ephemeralListeners = array_filter($this->ephemeralListeners, function ($l) use ($uid) {
            return $l['uid'] != $uid;
        });
    }

    /**
     * Add a callback handler for state events going to this room.
     *
     * @param callable $cb Callback called when an event arrives.
     * @param string|null $eventType The event_type to filter for.
     */
    public function addStateListener(callable $cb, ?string $eventType = null) {
        $this->stateListeners[] = [
            'callback' => $cb,
            'event_type' => $eventType,
        ];
    }

    protected function putEvent(array $event) {
        $this->events[] = $event;
        if (count($this->events) > $this->eventHistoryLimit) {
            array_pop($this->events);
        }
        if (array_key_exists('state_event', $event)) {
            $this->processStateEvent($event);
        }
        // Dispatch for room-specific listeners
        foreach ($this->listeners as $l) {
            if (!$l['event_type'] || $l['event_type'] == $event['event_type']) {
                $l['cb']($this, $event);
            }
        }
    }

    protected function putEphemeralEvent(array $event) {
        // Dispatch for room-specific listeners
        foreach ($this->ephemeralListeners as $l) {
            if (!$l['event_type'] || $l['event_type'] == $event['event_type']) {
                $l['cb']($this, $event);
            }
        }
    }

    /**
     * Get the most recent events for this room.
     *
     * @return array
     */
    public function getEvents(): array {
        return $this->events;
    }

    /**
     * Invite a user to this room.
     *
     * @param string $userId
     * @return bool Whether invitation was sent.
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     */
    public function inviteUser(string $userId): bool {
        try {
            $this->api()->inviteUser($this->roomId, $userId);
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Kick a user from this room.
     *
     * @param string $userId The matrix user id of a user.
     * @param string $reason A reason for kicking the user.
     * @return bool Whether user was kicked.
     * @throws Exceptions\MatrixException
     */
    public function kickUser(string $userId, string $reason = ''): bool {
        try {
            $this->api()->kickUser($this->roomId, $userId, $reason);
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Ban a user from this room.
     *
     * @param string $userId The matrix user id of a user.
     * @param string $reason A reason for banning the user.
     * @return bool Whether user was banned.
     * @throws Exceptions\MatrixException
     */
    public function banUser(string $userId, string $reason = ''): bool {
        try {
            $this->api()->banUser($this->roomId, $userId, $reason);
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Leave the room.
     *
     * @return bool Leaving the room was successful.
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     */
    public function leave() {
        try {
            $this->api()->leaveRoom($this->roomId);
            $this->client->forgetRoom($this->roomId);
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Updates $this->name and returns true if room name has changed.
     * @return bool
     * @throws Exceptions\MatrixException
     */
    public function updateRoomName() {
        try {
            $response = $this->api()->getRoomName($this->roomId);
            $newName = array_get($response, 'name', $this->name);
            $this->name = $newName;
            if ($this->name != $newName) {
                $this->name = $newName;
                return true;
            }
        } catch (MatrixRequestException $e) {
        }

        return false;
    }

    /**
     * Return True if room name successfully changed.
     *
     * @param string $name
     * @return bool
     * @throws Exceptions\MatrixException
     */
    public function setRoomName(string $name) {
        try {
            $this->api()->setRoomName($this->roomId, $name);
            $this->name = $name;
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Send a state event to the room.
     *
     * @param string $eventType The type of event that you are sending.
     * @param array $content An object with the content of the message.
     * @param string $stateKey Optional. A unique key to identify the state.
     * @throws Exceptions\MatrixException
     */
    public function sendStateEvent(string $eventType, array $content, string $stateKey = '') {
        $this->api()->sendStateEvent($this->roomId, $eventType, $content, $stateKey);
    }

    /**
     * Updates $this->topic and returns true if room topic has changed.
     *
     * @return bool
     * @throws Exceptions\MatrixException
     */
    public function updateRoomTopic() {
        try {
            $response = $this->api()->getRoomTopic($this->roomId);
            $oldTopic = $this->topic;
            $this->topic = array_get($response, 'topic', $this->topic);
        } catch (MatrixRequestException $e) {
            return false;
        }

        return $oldTopic == $this->topic;
    }

    /**
     * Return True if room topic successfully changed.
     *
     * @param string $topic
     * @return bool
     * @throws Exceptions\MatrixException
     */
    public function setRoomTopic(string $topic) {
        try {
            $this->api()->setRoomTopic($this->roomId, $topic);
            $this->topic = $topic;
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Get aliases information from room state.
     *
     * @return bool True if the aliases changed, False if not
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     */
    public function updateAliases() {
        try {
            $response = $this->api()->getRoomState($this->roomId);
            $oldAliases = $this->aliases;
            foreach ($response as $chunk) {
                if ($aliases = array_get($chunk, 'content.aliases')) {
                    $this->aliases = $aliases;
                    return $this->aliases == $oldAliases;
                }
            }
        } catch (MatrixRequestException $e) {
            return false;
        }
    }

    /**
     * Add an alias to the room and return True if successful.
     *
     * @param string $alias
     * @return bool
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     */
    public function addRoomAlias(string $alias) {
        try {
            $this->api()->setRoomAlias($this->roomId, $alias);
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    public function getJoinedMembers() {
        if ($this->_members) {
            return array_values($this->_members);
        }
        $response = $this->api()->getRoomMembers($this->roomId);
        foreach ($response['chunk'] as $event) {
            if (array_get($event, 'event.membership') == 'join') {
                $userId = $event['state_key'];
                $this->addMember($userId, array_get($event, 'content.displayname'));
            }
        }

        return array_values($this->_members);
    }

    protected function addMember(string $userId, ?string $displayname) {
        if ($displayname) {
            $this->membersDisplaynames[$userId] = $displayname;
        }
        if (array_key_exists($userId, $this->_members)) {
            return;
        }
        if (array_key_exists($userId, $this->client->users)) {
            $this->_members[$userId] = $this->client->users[$userId];
            return;
        }
        $this->_members[$userId] = new User($this->api(), $userId, $displayname);
        $this->client->users[$userId] = $this->_members[$userId];
    }

    /**
     * Backfill handling of previous messages.
     *
     * @param bool $reverse When false messages will be backfilled in their original
     *          order (old to new), otherwise the order will be reversed (new to old).
     * @param int $limit Number of messages to go back.
     * @throws Exceptions\MatrixException
     * @throws Exceptions\MatrixHttpLibException
     * @throws MatrixRequestException
     */
    public function backfillPreviousMessages(bool $reverse = false, int $limit = 10) {
        $res = $this->api()->getRoomMessages($this->roomId, $this->prevBatch, 'b', $limit);
        $events = $res['chunk'];
        if (!$reverse) {
            $events = array_reverse($events);
        }
        foreach ($events as $event) {
            $this->putEvent($event);
        }
    }

    /**
     * Modify the power level for a subset of users
     *
     * @param array $users Power levels to assign to specific users, in the form
     *          {"@name0:host0": 10, "@name1:host1": 100, "@name3:host3", None}
     *          A level of None causes the user to revert to the default level
     *          as specified by users_default.
     * @param int $userDefault Default power level for users in the room
     * @return bool
     * @throws Exceptions\MatrixException
     */
    public function modifyUserPowerLevels(array $users = null, int $userDefault = null) {
        try {
            $content = $this->api()->getPowerLevels($this->roomId);
            if ($userDefault) {
                $content['user_default'] = $userDefault;
            }

            if ($users) {
                if (array_key_exists('users', $content)) {
                    $content['users'] = array_merge($content['users'], $content);
                } else {
                    $content['users'] = $users;
                }

                // Remove any keys with value null
                foreach ($content['users'] as $user => $pl) {
                    if (!$pl) {
                        unset($content['users'][$user]);
                    }
                }
            }

            $this->api()->setPowerLevels($this->roomId, $content);
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Modifies room power level requirements.
     *
     * @param array $events Power levels required for sending specific event types,
     *          in the form {"m.room.whatever0": 60, "m.room.whatever2": None}.
     *          Overrides events_default and state_default for the specified
     *          events. A level of None causes the target event to revert to the
     *          default level as specified by events_default or state_default.
     * @param array $extra Key/value pairs specifying the power levels required for
     *          various actions:
     *
     *          - events_default(int): Default level for sending message events
     *          - state_default(int): Default level for sending state events
     *          - invite(int): Inviting a user
     *          - redact(int): Redacting an event
     *          - ban(int): Banning a user
     *          - kick(int): Kicking a user
     * @return bool
     * @throws Exceptions\MatrixException
     */
    public function modifyRequiredPowerLevels(array $events = [], array $extra = []) {
        try {
            $content = $this->api()->getPowerLevels($this->roomId);
            $content = array_merge($content, $extra);
            foreach ($content as $k => $v) {
                if (!$v) {
                    unset($content[$k]);
                }
            }

            if ($events) {
                if (array_key_exists('events', $content)) {
                    $content["events"] = array_merge($content["events"], $events);
                } else {
                    $content["events"] = $events;
                }

                // Remove any keys with value null
                foreach ($content['event'] as $event => $pl) {
                    if (!$pl) {
                        unset($content['event'][$event]);
                    }
                }
            }

            $this->api()->setPowerLevels($this->roomId, $content);
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Set how the room can be joined.
     *
     * @param bool $inviteOnly If True, users will have to be invited to join
     *          the room. If False, anyone who knows the room link can join.
     * @return bool True if successful, False if not
     * @throws Exceptions\MatrixException
     */
    public function setInviteOnly(bool $inviteOnly) {
        $joinRule = $inviteOnly ? 'invite' : 'public';
        try {
            $this->api()->setJoinRule($this->roomId, $joinRule);
            $this->inviteOnly = $inviteOnly;
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Set whether guests can join the room and return True if successful.
     *
     * @param bool $allowGuest
     * @return bool
     * @throws Exceptions\MatrixException
     */
    public function setGuestAccess(bool $allowGuest) {
        $guestAccess = $allowGuest ? 'can_join' : 'forbidden';
        try {
            $this->api()->setGuestAccess($this->roomId, $guestAccess);
            $this->guestAccess = $allowGuest;
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Enables encryption in the room.
     *
     * NOTE: Once enabled, encryption cannot be disabled.
     *
     * @return bool True if successful, False if not
     * @throws Exceptions\MatrixException
     */
    public function enableEncryption() {
        try {
            $this->sendStateEvent('m.room.encryption', ['algorithm' => 'm.megolm.v1.aes-sha2']);
            $this->encrypted = true;
        } catch (MatrixRequestException $e) {
            return false;
        }

        return true;
    }

    protected function processStateEvent(array $stateEvent) {
        if (!array_key_exists('type', $stateEvent)) {
            return;
        }
        $etype = $stateEvent['type'];
        $econtent = $stateEvent['content'];
        $clevel = $this->client->cacheLevel();

        // Don't keep track of room state if caching turned off
        if ($clevel >= Cache::SOME) {
            switch ($etype) {
                case 'm.room.name':
                    $this->name = array_get($econtent, 'name');
                    break;
                case 'm.room.canonical_alias':
                    $this->canonicalAlias = array_get($econtent, 'alias');
                    break;
                case 'm.room.topic':
                    $this->topic = array_get($econtent, 'topic');
                    break;
                case 'm.room.aliases':
                    $this->aliases = array_get($econtent, 'aliases');
                    break;
                case 'm.room.join_rules':
                    $this->inviteOnly = $econtent["join_rule"] == "invite";
                    break;
                case 'm.room.guest_access':
                    $this->guestAccess = $econtent["guest_access"] == "can_join";
                    break;
                case 'm.room.encryption':
                    $this->encrypted = array_get($econtent, 'algorithm') ? true : $this->encrypted;
                    break;
                case 'm.room.member':
                    // tracking room members can be large e.g. #matrix:matrix.org
                    if ($clevel == Cache::ALL) {
                        if ($econtent['membership'] == 'join') {
                            $userId = $stateEvent['state_key'];
                            $this->addMember($userId, array_get($econtent, 'displayname'));
                        } elseif (in_array(econtent["membership"], ["leave", "kick", "invite"])) {
                            unset($this->_members[array_get($stateEvent, 'state_key')]);
                        }
                    }
                    break;
            }
        }

        foreach ($this->stateListeners as $listener) {
            if (!$listener['event_type'] || $listener['event_type'] == $stateEvent['type']) {
                $listener['cb']($stateEvent);
            }
        }
    }

    public function getMembersDisplayNames(): array {
        return $this->membersDisplaynames;
    }

    protected function api(): MatrixHttpApi {
        return $this->client->api();
    }


}