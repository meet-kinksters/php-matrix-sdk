<?php

namespace Aryess\PhpMatrixSdk\Exceptions;

/**
 * The home server returned an error response.
 *
 * @package Aryess\PhpMatrixSdk\Exceptions
 */
class MatrixRequestException extends MatrixException {

    protected $httpCode;
    protected $content;

    public function __construct(int $code = 0, string $content = "") {
        parent::__construct("$code: $content");
        $this->httpCode = $code;
        $this->content = $content;
    }

    /**
     * @return int
     */
    public function getHttpCode(): int {
        return $this->httpCode;
    }

    /**
     * @return string
     */
    public function getContent(): string {
        return $this->content;
    }
}