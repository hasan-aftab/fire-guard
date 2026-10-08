<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends mail through the Brevo transactional API using an API key,
 * so no SMTP relay, port 587 or SMTP login is involved.
 */
class BrevoTransport extends AbstractTransport
{
    public const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly ?string $key,
        private readonly int $timeout = 30,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'brevo';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('Brevo can only send Mime Email messages.');
        }

        if (! $this->key) {
            throw new TransportException('BREVO_API_KEY is not configured.');
        }

        $response = Http::withToken($this->key)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout($this->timeout)
            ->post(self::ENDPOINT, $this->payload($email));

        if (! $response->successful()) {
            $reason = $response->json('message');

            throw new TransportException('Brevo rejected the message: '.(is_string($reason) ? $reason : $response->reason()));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Email $email): array
    {
        $from = $email->getFrom()[0] ?? null;

        if (! $from instanceof Address) {
            throw new TransportException('No From address is configured for the message.');
        }

        $payload = [
            'sender' => ['email' => $from->getAddress(), 'name' => $from->getName()],
            'to' => $this->addresses($email->getTo()),
            'subject' => (string) $email->getSubject(),
            'htmlContent' => $this->body($email->getHtmlBody()),
            'textContent' => $this->body($email->getTextBody()),
            'attachments' => [],
        ];

        if ($email->getReplyTo() !== []) {
            $payload['replyTo'] = $this->addresses($email->getReplyTo())[0];
        }
        if ($email->getCc() !== []) {
            $payload['cc'] = $this->addresses($email->getCc());
        }
        if ($email->getBcc() !== []) {
            $payload['bcc'] = $this->addresses($email->getBcc());
        }

        foreach ($email->getAttachments() as $attachment) {
            $payload['attachments'][] = [
                'name' => (string) $attachment->getFilename(),
                'content' => base64_encode($attachment->getBody()),
                'type' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
            ];
        }

        /** @var array<string, mixed> $payload */
        return array_filter($payload, fn (mixed $value): bool => $value !== '' && $value !== [] && $value !== null);
    }

    /**
     * @param  array<int|string, Address>  $addresses
     * @return list<array{email: string, name: string}>
     */
    protected function addresses(array $addresses): array
    {
        return array_map(
            fn (Address $address): array => ['email' => $address->getAddress(), 'name' => $address->getName()],
            array_values($addresses),
        );
    }

    protected function body(mixed $body): string
    {
        if (is_string($body)) {
            return $body;
        }

        if (is_resource($body)) {
            return (string) stream_get_contents($body);
        }

        return '';
    }
}
