#!/usr/bin/env php
<?php

declare(strict_types=1);

use MyVendor\OutboxDemo\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

exit((new Bootstrap())('cli-hal-api-app', $GLOBALS, $_SERVER));
