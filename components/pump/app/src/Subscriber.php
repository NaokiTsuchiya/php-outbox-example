<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Attribute;
use Ray\Di\Di\Qualifier;

#[Attribute, Qualifier]
final class Subscriber
{
}
