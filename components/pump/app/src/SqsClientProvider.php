<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\Sqs\SqsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Ray\Di\ProviderInterface;

/** 環境変数から SQS エンドポイントを読み取り SqsClient を提供する */
class SqsClientProvider implements ProviderInterface
{
    public function get(): SqsClient
    {
        // Swoole コルーチン内では CurlMultiHandler がハンドルを int にキャストして失敗するため
        // CurlHandler（シングルリクエスト）を使用する
        return new SqsClient([
            'region'       => 'elasticmq',
            'version'      => 'latest',
            'endpoint'     => $_ENV['SQS_ENDPOINT'],
            'credentials'  => ['key' => 'dummy', 'secret' => 'dummy'],
            'http_handler' => new GuzzleHandler(new Client([
                'handler' => HandlerStack::create(new CurlHandler()),
            ])),
        ]);
    }
}
