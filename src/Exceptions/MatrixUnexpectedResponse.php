<?php

namespace MatrixPhp\Exceptions;

/**
 * The home server gave an unexpected response.
 *
 * @package MatrixPhp\Exceptions
 */
class MatrixUnexpectedResponse extends MatrixException {

    protected $content;

    function __construct(string $content = '') {
        parent::__construct($content);
        $this->content = $content;
    }

    /**
     * @return string
     */
    public function getContent(): string {
        return $this->content;
    }
}
