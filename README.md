# Matrix client SDK for php
[![Software License][ico-license]](LICENSE.md)

This is a Matrix client-server SDK for php 7.0+, mostly copied from [matrix-org/matrix-python-sdk][python-pck]

This package is still a work in progress, and at the current time, not everything has been ported:
- Missing E2E encryption, need php bindings for the OLM library
- No live sync, because I'm not going to go into php multithreading
- Unit tests for the client

## Installation

```
composer require aryess/php-matrix-sdk
```

## Usage
Client:
```php
require('vendor/autoload.php');
use Aryess\PhpMatrixSdk\MatrixClient;

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
use Aryess\PhpMatrixSdk\MatrixHttpApi;

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

If you discover any security related issues, please email aryess@github.com instead of using the issue tracker.

## Credits

- [Yoann Celton][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/Aryess/PhpMatrixSdk.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/Aryess/PhpMatrixSdk/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/Aryess/PhpMatrixSdk.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/Aryess/PhpMatrixSdk.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/Aryess/PhpMatrixSdk.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/Aryess/PhpMatrixSdk
[link-travis]: https://travis-ci.org/Aryess/PhpMatrixSdk
[link-scrutinizer]: https://scrutinizer-ci.com/g/Aryess/PhpMatrixSdk/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/Aryess/PhpMatrixSdk
[link-downloads]: https://packagist.org/packages/Aryess/PhpMatrixSdk
[link-author]: https://github.com/aryess
[link-contributors]: ../../contributors
[python-pck]: https://github.com/matrix-org/matrix-python-sdk
