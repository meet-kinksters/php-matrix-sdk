<?php

namespace Aryess\PhpMatrixSdk;

/**
 * Cache constants used when instantiating Matrix Client to specify level of caching
 *
 *  TODO: rather than having CACHE.NONE as arg to MatrixClient, there should be a separate
 *      LightweightMatrixClient that only implements global listeners and doesn't hook into
 *      User, Room, etc. classes at all.
 * @package Aryess\PhpMatrixSdk
 */
class Cache {
    const NONE = -1;
    const SOME = 0;
    const ALL = 1;

    public static $levels = [
        self::NONE,
        self::SOME,
        self::ALL,
    ];
}