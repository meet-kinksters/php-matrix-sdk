<?php

namespace MatrixPhp\Exceptions;

/**
 * The home server returned an error response.
 *
 * @package MatrixPhp\Exceptions
 */
class MatrixRequestException extends MatrixException {

    protected $content;
    public readonly ?string $errCode;

    public function __construct(int $code = 0, string $content = "") {
        parent::__construct($content, $code);
        $this->httpCode = $code;
        $this->content = $content;
        try {
            $decoded = \json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
            $this->errCode = $decoded['errcode'] ?? NULL;
        }
        catch (\JsonException) {
            $this->errCode = NULL;
        }
    }

    /**
     * @return int
     */
    public function getHttpCode(): int {
        return $this->getCode();
    }

    /**
     * @return string
     */
    public function getContent(): string {
        return $this->content;
    }
}
