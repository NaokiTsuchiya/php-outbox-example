#!/usr/bin/env php
<?php

declare(strict_types=1);

use MyVendor\OutboxConsumer\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

exit((new Bootstrap())('cli-hal-api-app', $GLOBALS, $_SERVER));
