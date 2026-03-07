<?php

declare(strict_types=1);

use MyVendor\OutboxDemo\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

exit((new Bootstrap())(
    'hal-api-app',
    $GLOBALS,
    $_SERVER
));
