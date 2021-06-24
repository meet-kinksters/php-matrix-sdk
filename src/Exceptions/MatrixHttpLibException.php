<?php

namespace Aryess\PhpMatrixSdk\Exceptions;

/**
 * The library used for http requests raised an exception.
 *
 * @package Aryess\PhpMatrixSdk\Exceptions
 */
class MatrixHttpLibException extends MatrixException {

    public function __construct(\Exception $originalException, string $method, string $endpoint) {
        $msg = "Something went wrong in %s requesting %s: %s";
        $msg = sprintf($msg, $method, $endpoint, $originalException);
        parent::__construct($msg, $originalException->getCode(), $originalException);
    }
}