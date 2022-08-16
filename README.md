# Matrix client SDK for PHP
[![Software License][ico-license]](LICENSE.md)
[![Software Version][ico-version]](https://packagist.org/packages/meet-kinksters/php-matrix-sdk)
![Software License][ico-downloads]

This is a Matrix client-server SDK for php 7.4+, initially copied from
[matrix-org/matrix-python-sdk][python-pck].

This package is still a work in progress, and at the current time, not everything has been ported:
- Missing E2E encryption, need php bindings for the OLM library
- Live sync
- Unit tests for the client

## Installation

```
composer require meet-kinksters/php-matrix-sdk
```

## Usage
Client:
```php
require('vendor/autoload.php');
use MatrixPhp\MatrixClient;

$client = new MatrixClient("http://localhost:8008");

// New user
$token = $client->registerWithPassword("foobar", "monkey");

// Existing user
$token = $client->login("foobar", "monkey");

$room = $client->createRoom("my_room_alias");
$room->sendText("Hello!");
```

API:
```php
require('vendor/autoload.php');
use MatrixPhp\MatrixHttpApi;

$matrix = new MatrixHttpApi("http://localhost:8008", $sometoken);

$response = $matrix->sendMessage("!roomid:matrix.org", "Hello!");
```

##Structure
The SDK is split into two modules: ``api`` and ``client``.

###API
This contains the raw HTTP API calls and has minimal business logic. You can
set the access token (``token``) to use for requests as well as set a custom
transaction ID (``txn_id``) which will be incremented for each request.

###Client
This encapsulates the API module and provides object models such as ``Room``.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email brad@kinksters.dating
instead of using the issue tracker.

## Credits

- [Brad Jones](https://github.com/bradjones1) at [Meet Kinksters](https://tech.kinksters.dating)
- [Yoann Celton](https://github.com/Aryess) (initial port)
- [All Contributors](https://github.com/meet-kinksters/php-matrix-sdk/graphs/contributors)

## License

[MIT License](LICENSE.md).

[ico-version]: https://img.shields.io/packagist/v/meet-kinksters/php-matrix-sdk.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/meet-kinksters/php-matrix-sdk.svg?style=flat-square
[python-pck]: https://github.com/matrix-org/matrix-python-sdk
