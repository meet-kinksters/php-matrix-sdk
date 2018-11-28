<?php

namespace Aryess\PhpMatrixSdk;

use Aryess\PhpMatrixSdk\Exceptions\ValidationException;

class Util {

    /**
     * Check if provided roomId is valid
     *
     * @param string $roomId
     * @throws ValidationException
     */
    public static function checkRoomId(string $roomId) {
        if (strpos($roomId, '!') !== 0) {
            throw new ValidationException("RoomIDs start with !");
        }

        if (strpos($roomId, ':') === false) {
            throw new ValidationException("RoomIDs must have a domain component, seperated by a :");
        }
    }

    /**
     * Check if provided userId is valid
     *
     * @param string $userId
     * @throws ValidationException
     */
    public static function checkUserId(string $userId) {
        if (strpos($userId, '@') !== 0) {
            throw new ValidationException("UserIDs start with @");
        }

        if (strpos($userId, ':') === false) {
            throw new ValidationException("UserIDs must have a domain component, seperated by a :");
        }
    }
}