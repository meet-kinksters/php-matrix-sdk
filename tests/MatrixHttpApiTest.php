<?php

namespace Aryess\PhpMatrixSdk;

use Aryess\PhpMatrixSdk\Exceptions\MatrixException;
use Aryess\PhpMatrixSdk\Exceptions\MatrixHttpLibException;
use Aryess\PhpMatrixSdk\Exceptions\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use phpDocumentor\Reflection\DocBlock\Tags\Param;

class MatrixHttpApiTest extends BaseTestCase {
    protected $userId = "@alice:matrix.org";
    protected $roomId = '#foo:matrix.org';
    protected $token = "Dp0YKRXwx0iWDhFj7lg3DVjwsWzGcUIgARljgyAip2JD8qd5dSaWcxowTKEFetPulfLijAhv8eO"
    . "mUSScyGcWgZyNMRTBmoJ0RFc0HotPvTBZU98yKRLtat7V43aCpFmK";
    protected $testPath = "/account/whoami";
    protected $deviceId = "QBUAZIFURK";
    protected $displayName = "test_name";
    protected $authBody = [
        "auth" => [
            "type" => "example.type.foo",
            "session" => "xxxxx",
            "example_credential" => "verypoorsharedsecret"
        ]
    ];
    protected $oneTimeKeys = ["curve25519:AAAAAQ" => "/qyvZvwjiTxGdGU0RCguDCLeR+nmsb3FfNG3/Ve4vU8"];
    protected $deviceKeys = [
        "user_id" => "@alice:example.com",
        "device_id" => "JLAFKJWSCS",
        "algorithms" => [
            "m.olm.curve25519-aes-sha256",
            "m.megolm.v1.aes-sha"
        ],
        "keys" => [
            "curve25519:JLAFKJWSCS" => "3C5BFWi2Y8MaVvjM8M22DBmh24PmgR0nPvJOIArzgyI",
            "ed25519:JLAFKJWSCS" => "lEuiRJBit0IG6nUf5pUzWTUEsRVVe/HJkoKuEww9ULI"
        ],
        "signatures" => [
            "@alice:example.com" => [
                "ed25519:JLAFKJWSCS" => ("dSO80A01XiigH3uBiDVx/EjzaoycHcjq9lfQX0uWsqxl2gi"
                    . "MIiSPR8a4d291W1ihKJL/a+myXS367WT6NAIcBA")
            ]
        ]
    ];

    protected $mxurl = "mxc://example.com/OonjUOmcuVpUnmOWKtzPmAFe";
    /**
     * @var MatrixHttpApi
     */
    protected $api;

    protected function setUp(): void 
    {
        parent::setUp();
        $this->api = new MatrixHttpApi('http://example.com');
    }

    ///////////////////////////
    // class TestTagsApi
    ///////////////////////////
    public function testGetUserTags() {
        $tagsUrl = "http://example.com/_matrix/client/r0/user/%40alice%3Amatrix.org/rooms/%23foo%3Amatrix.org/tags";
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->getUserTags($this->userId, $this->roomId);
        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals($tagsUrl, (string)$req->getUri());
    }

    public function testAddUserTag() {
        $tagsUrl = "http://example.com/_matrix/client/r0/user/%40alice%3Amatrix.org/rooms/%23foo%3Amatrix.org/tags/foo";
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->addUserTag($this->userId, $this->roomId, 'foo', null, ["order" => "5"]);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('PUT', $req->getMethod());
        $this->assertEquals($tagsUrl, (string)$req->getUri());
    }

    public function testRemoveUserTag() {
        $tagsUrl = "http://example.com/_matrix/client/r0/user/%40alice%3Amatrix.org/rooms/%23foo%3Amatrix.org/tags/foo";
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->removeUserTag($this->userId, $this->roomId, 'foo');

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('DELETE', $req->getMethod());
        $this->assertEquals($tagsUrl, (string)$req->getUri());
    }

    ///////////////////////////
    // class TestAccountDataApi
    ///////////////////////////
    public function testSetAccountData() {
        $accountDataUrl = "http://example.com/_matrix/client/r0/user/%40alice%3Amatrix.org/account_data/foo";
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->setAccountData($this->userId, 'foo', ['bar' => 1]);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('PUT', $req->getMethod());
        $this->assertEquals($accountDataUrl, (string)$req->getUri());
    }

    public function testSetRoomAccountData() {
        $accountDataUrl = 'http://example.com/_matrix/client/r0/user/%40alice%3Amatrix.org/';
        $accountDataUrl .= 'rooms/%23foo%3Amatrix.org/account_data/foo';
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->setRoomAccountData($this->userId, $this->roomId, 'foo', ['bar' => 1]);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('PUT', $req->getMethod());
        $this->assertEquals($accountDataUrl, (string)$req->getUri());
    }

    ///////////////////////////
    // class TestAccountDataApi
    ///////////////////////////
    public function testUnban() {
        $unbanUrl = 'http://example.com/_matrix/client/r0/rooms/%23foo%3Amatrix.org/unban';
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->unbanUser($this->roomId, $this->userId);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals($unbanUrl, (string)$req->getUri());
    }

    ///////////////////////////
    // class TestDeviceApi
    ///////////////////////////
    public function testGetDevices() {
        $getDevicesUrl = "http://example.com/_matrix/client/r0/devices";
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->getDevices();

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals($getDevicesUrl, (string)$req->getUri());
    }

    public function testGetDevice() {
        $getDevicesUrl = "http://example.com/_matrix/client/r0/devices/" . $this->deviceId;
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->getDevice($this->deviceId);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals($getDevicesUrl, (string)$req->getUri());
    }

    public function testUpdateDeviceInfo() {
        $getDevicesUrl = "http://example.com/_matrix/client/r0/devices/" . $this->deviceId;
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->updateDeviceInfo($this->deviceId, $this->displayName);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('PUT', $req->getMethod());
        $this->assertEquals($getDevicesUrl, (string)$req->getUri());
    }

    public function testDeleteDevice() {
        $getDevicesUrl = "http://example.com/_matrix/client/r0/devices/" . $this->deviceId;
        $container = [];
        // Test for 401 status code of User-Interactive Auth API
        $handler = $this->getMockClientHandler([new Response(401, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->expectException(MatrixHttpLibException::class);

        try {
            $this->api->deleteDevice($this->authBody, $this->deviceId);
        } catch (MatrixHttpLibException $e) {
            /** @var Request $req */
            $req = array_get($container, '0.request');

            $this->assertEquals('DELETE', $req->getMethod());
            $this->assertEquals($getDevicesUrl, (string)$req->getUri());

            throw $e;
        }
    }

    public function testDeleteDevices() {
        $getDevicesUrl = "http://example.com/_matrix/client/r0/delete_devices/";
        $container = [];
        // Test for 401 status code of User-Interactive Auth API
        $handler = $this->getMockClientHandler([new Response(401, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->expectException(MatrixHttpLibException::class);

        $this->api->deleteDevices($this->authBody, [$this->deviceId]);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals($getDevicesUrl, (string)$req->getUri());
    }

    ///////////////////////////
    // class TestKeysApi
    ///////////////////////////
    /**
     * @param array $args
     * @throws Exceptions\MatrixRequestException
     * @throws MatrixException
     * @throws MatrixHttpLibException
     * @dataProvider uploadKeysProvider
     */
    public function testUploadKeys(array $args) {
        $uploadKeysUrl = 'http://example.com/_matrix/client/r0/keys/upload';
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->uploadKeys($args);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals($uploadKeysUrl, (string)$req->getUri());
    }

    public function uploadKeysProvider(): array {
        return [
            [[]],
            [['device_keys' => $this->deviceKeys]],
            [['one_time_keys' => $this->oneTimeKeys]],
        ];
    }

    public function testQueryKeys() {
        $queryKeyUrl = 'http://example.com/_matrix/client/r0/keys/query';
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->queryKeys([$this->userId => $this->deviceId], 10);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals($queryKeyUrl, (string)$req->getUri());
    }

    public function testClaimKeys() {
        $claimKeysUrl = 'http://example.com/_matrix/client/r0/keys/claim';
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $keyRequest = [$this->userId => [$this->deviceId => 'algo']];
        $this->api->claimKeys($keyRequest, 1000);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals($claimKeysUrl, (string)$req->getUri());
    }

    public function testKeyChange() {
        $keyChangeUrl = 'http://example.com/_matrix/client/r0/keys/changes';
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->keyChanges('s72594_4483_1934', 's75689_5632_2435');

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals($keyChangeUrl, explode('?', (string)$req->getUri())[0]);
    }

    ///////////////////////////
    // class TestSendToDeviceApi
    ///////////////////////////
    public function testSendToDevice() {
        $txnId = $this->invokePrivateMethod($this->api, 'makeTxnId');
        $sendToDeviceUrl = "http://example.com/_matrix/client/r0/sendToDevice/m.new_device/" . $txnId;
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], '{}')], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $payload = [$this->userId => [$this->deviceId => ['test' => 1]]];
        $this->api->sendToDevice('m.new_device', $payload, $txnId);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('PUT', $req->getMethod());
        $this->assertEquals($sendToDeviceUrl, (string)$req->getUri());
    }

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
        $this->assertEquals('php-matrix-sdk/' . $mapi::VERSION, $req->getHeader('User-Agent')[0]);
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

    ///////////////////////////
    // class TestMediaApi
    ///////////////////////////
    public function testMediaDownload() {
        $dlUrl = "http://example.com/_matrix/media/r0/download/" . substr($this->mxurl, 6);
        $container = [];
        $response = new Response(200, [
            'Content-Type' => 'application/php'
        ], file_get_contents('./tests/TestHelper.php'));
        $handler = $this->getMockClientHandler([$response], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->mediaDownload($this->mxurl, false);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals($dlUrl, explode('?', (string)$req->getUri())[0]);
    }

    public function testMediaDownloadWrongUrl() {
        $this->expectException(ValidationException::class);

        $this->api->mediaDownload(substr($this->mxurl, 6));
    }

    public function testGetThumbnail() {
        $dlUrl = "http://example.com/_matrix/media/r0/thumbnail/" . substr($this->mxurl, 6);
        $container = [];
        $response = new Response(200, [
            'Content-Type' => 'application/php'
        ], file_get_contents('./tests/TestHelper.php'));
        $handler = $this->getMockClientHandler([$response], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->getThumbnail($this->mxurl, 28, 28, 'scale', false);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals($dlUrl, explode('?', (string)$req->getUri())[0]);
    }

    public function testThumbnailWrongUrl() {
        $this->expectException(ValidationException::class);

        $this->api->getThumbnail(substr($this->mxurl, 6), 28, 28);
    }

    public function testThumbnailWrongMethod() {
        $this->expectException(ValidationException::class);

        $this->api->getThumbnail($this->mxurl, 28, 28, 'cut', false);
    }

    public function testGetUrlPreview() {
        $mediaUrl = "http://example.com/_matrix/media/r0/preview_url";
        $r = json_encode(TestHelper::EXAMPLE_PREVIEW_URL);
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], $r)], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->getUrlPreview("https://google.com/", 1510610716656);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals($mediaUrl, explode('?', (string)$req->getUri())[0]);
    }


    ///////////////////////////
    // class TestRoomApi
    ///////////////////////////
    /**
     * @dataProvider createRoomVisibilityProvider
     */
    public function testCreateRoomVisibility(bool $isPublic, string $visibility) {
        $createRoomUrl = "http://example.com/_matrix/client/r0/createRoom";
        $container = [];
        $r = '{"room_id": "!sefiuhWgwghwWgh:example.com"}';
        $handler = $this->getMockClientHandler([new Response(200, [], $r)], $container);
        $this->api->setClient(new Client(['handler' => $handler]));
        $this->api->createRoom("#test:example.com", 'test', $isPublic);

        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals($createRoomUrl, (string)$req->getUri());
        $body = json_decode($req->getBody()->getContents(), true);
        $this->assertEquals("#test:example.com", $body['room_alias_name']);
        $this->assertEquals($visibility, $body['visibility']);
        $this->assertEquals('test', $body['name']);
    }

    public function createRoomVisibilityProvider(): array {
        return [
            [true, 'public'],
            [false, 'private'],
        ];
    }

    /**
     * @dataProvider createRoomFederationProvider
     */
    public function testCreateRoomFederation(bool $isFederated) {
        $createRoomUrl = "http://example.com/_matrix/client/r0/createRoom";
        $container = [];
        $r = '{"room_id": "!sefiuhWgwghwWgh:example.com"}';
        $handler = $this->getMockClientHandler([new Response(200, [], $r)], $container);
        $this->api->setClient(new Client(['handler' => $handler]));

        $this->api->createRoom("#test:example.com", 'test', false, [], $isFederated);
        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals($createRoomUrl, (string)$req->getUri());
        $body = json_decode($req->getBody()->getContents(), true);
        $this->assertEquals("#test:example.com", $body['room_alias_name']);
        $this->assertEquals($isFederated, array_key_exists('m.federate', array_get($body, 'creation_content', [])));
    }


    public function createRoomFederationProvider(): array {
        return [
            [true],
            [false],
        ];
    }

    ///////////////////////////
    // class TestRoomApi
    ///////////////////////////
    public function testWhoami() {
        $mapi = new MatrixHttpApi("http://example.com", $this->token);
        $whoamiUrl = "http://example.com/_matrix/client/r0/account/whoami";

        $r = sprintf('{"user_id": "%s"}', $this->userId);
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], $r)], $container);
        $mapi->setClient(new Client(['handler' => $handler]));

        $mapi->whoami();
        /** @var Request $req */
        $req = array_get($container, '0.request');

        $this->assertEquals('GET', $req->getMethod());
        $this->assertContains($req->getRequestTarget(), $whoamiUrl);
    }

    public function testWhoamiUnauth() {
        $this->expectException(MatrixException::class);

        $r = sprintf('{"user_id": "%s"}', $this->userId);
        $container = [];
        $handler = $this->getMockClientHandler([new Response(200, [], $r)], $container);
        $this->api->setClient(new Client(['handler' => $handler]));

        $this->api->whoami();
    }


}