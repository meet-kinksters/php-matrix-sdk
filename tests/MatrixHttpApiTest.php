<?php

namespace Aryess\PhpMatrixSdk;

use Aryess\PhpMatrixSdk\Exceptions\MatrixException;
use Aryess\PhpMatrixSdk\Exceptions\MatrixHttpLibException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;

class MatrixHttpApiTest extends BaseTestCase {
    protected $userId = "@alice:matrix.org";
    protected $token = "Dp0YKRXwx0iWDhFj7lg3DVjwsWzGcUIgARljgyAip2JD8qd5dSaWcxowTKEFetPulfLijAhv8eOmUSScyGcWgZyNMRTBmoJ0RFc0HotPvTBZU98yKRLtat7V43aCpFmK";
    protected $testPath = "/account/whoami";

    ///////////////////////////
    // class TestMainApi
    ///////////////////////////
    public function testSendTokenHeader() {
        $mapi = new MatrixHttpApi("http://example.com", $this->token);

        $r = sprintf('{"application/json": {"user_id": "%s"}}', $this->userId);
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], $r)], $container);
        $mapi->setClient(new Client(['handler' => $handler]));

        $this->invokePrivateMethod($mapi, 'send', ['GET', $this->testPath]);
        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals(sprintf('Bearer %s', $this->token), $req->getHeader('Authorization')[0]);
    }

    public function testSendUserAgentHeader() {
        $mapi = new MatrixHttpApi("http://example.com", $this->token);

        $r = sprintf('{"application/json": {"user_id": "%s"}}', $this->userId);
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], $r)], $container);
        $mapi->setClient(new Client(['handler' => $handler]));

        $this->invokePrivateMethod($mapi, 'send', ['GET', $this->testPath]);
        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals('php-matrix-sdk/'.$mapi::VERSION, $req->getHeader('User-Agent')[0]);
    }

    public function testSendTokenQuery() {
        $mapi = new MatrixHttpApi("http://example.com", $this->token, null, 500, false);

        $r = sprintf('{"application/json": {"user_id": "%s"}}', $this->userId);
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], $r)], $container);
        $mapi->setClient(new Client(['handler' => $handler]));

        $this->invokePrivateMethod($mapi, 'send', ['GET', $this->testPath]);
        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertContains($this->token, $req->getRequestTarget());
    }

    public function testSendUserId() {
        $mapi = new MatrixHttpApi("http://example.com", $this->token, $this->userId);

        $r = sprintf('{"application/json": {"user_id": "%s"}}', $this->userId);
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], $r)], $container);
        $mapi->setClient(new Client(['handler' => $handler]));

        $this->invokePrivateMethod($mapi, 'send', ['GET', $this->testPath]);
        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertContains(urlencode($this->userId), $req->getRequestTarget());
    }

    public function testSendUnsupMethod() {
        $this->expectException(MatrixException::class);
        $mapi = new MatrixHttpApi("http://example.com", $this->token, $this->userId);
        $this->invokePrivateMethod($mapi, 'send', ['GOT', $this->testPath]);
    }

    public function testSendRequestError() {
        $this->expectException(MatrixHttpLibException::class);
        $mapi = new MatrixHttpApi("http://example.com");
        $this->invokePrivateMethod($mapi, 'send', ['GET', $this->testPath]);
    }
}