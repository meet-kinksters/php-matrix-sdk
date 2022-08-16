<?php

namespace MatrixPhp\Crypto;

use MatrixPhp\MatrixHttpApi;

/**
 * OlmDevice stub for typehinting
 *
 * @package MatrixPhp\Crypto
 */
class OlmDevice {

    public function __construct(MatrixHttpApi $client, string $userId, ?string $deviceId, array &$encryptionConf) {
    }

    public function uploadIdentityKeys() {
    }

    public function uploadOneTimeKeys() {
    }

    public function updateOneTimeKeysCounts($device_one_time_keys_count) {

    }

}
