<?php

namespace Aryess\PhpMatrixSdk;

class Room {

    public function __construct(MatrixClient $client, string $roomId) {
    }

    public function getMembersDisplayNames(): array {
        return [];
    }

    public function setEncrytion(bool $true) {
    }
}