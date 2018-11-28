<?php

namespace Aryess\PhpMatrixSdk;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Middleware;

abstract class BaseTestCase extends \PHPUnit\Framework\TestCase {
    const MATRIX_V2_API_PATH = "/_matrix/client/r0";
    protected $userId = "@alice:matrix.org";

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     * @throws \ReflectionException
     */
    protected function invokePrivateMethod(&$object, $methodName, array $parameters = []) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    protected function getMockClientHandler(array $responses, array &$container): HandlerStack {
        $mock = new MockHandler($responses);
        $history = Middleware::history($container);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        return $handler;
    }
}