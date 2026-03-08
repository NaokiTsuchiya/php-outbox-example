<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Mail;

/**
 * ダミーメール実装: メール内容を stderr に出力する
 *
 * error_log() を使うことで Docker logs で捕捉可能にする
 */
class LogMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $body): void
    {
        error_log(sprintf('[LogMailer] to=%s subject=%s body=%s', $to, $subject, $body));
    }
}
