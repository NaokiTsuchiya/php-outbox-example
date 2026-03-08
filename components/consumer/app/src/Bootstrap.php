<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer;

use BEAR\Package\Injector;
use BEAR\Resource\ResourceInterface;
use BEAR\Sunday\Extension\Router\RouterInterface;
use BEAR\Sunday\Extension\Transfer\TransferInterface;
use Throwable;

final class Bootstrap
{
    public function __invoke(string $context, array $globals, array $server): int
    {
        $appDir = dirname(__DIR__);
        $injector = Injector::getInstance(__NAMESPACE__, $context, $appDir);
        $router = $injector->getInstance(RouterInterface::class);
        $request = $router->match($globals, $server);
        try {
            $resource = $injector->getInstance(ResourceInterface::class);
            $ro = $resource->{$request->method}->uri($request->path)($request->query);
            $responder = $injector->getInstance(TransferInterface::class);
            $ro->transfer($responder, []);
        } catch (Throwable $e) {
            http_response_code(500);
            error_log((string) $e);

            return 1;
        }

        return 0;
    }
}
