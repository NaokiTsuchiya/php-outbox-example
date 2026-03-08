<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Mail;

interface MailerInterface
{
    public function send(string $to, string $subject, string $body): void;
}
