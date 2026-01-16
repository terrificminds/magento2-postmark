<?php
/**
 * Postmark integration
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to opensource@ripen.com so we can send you a copy immediately.
 *
 * @category    Ripen
 * @package     Ripen_Postmark
 * @copyright   Copyright (c) SUMO Heavy Industries, LLC
 * @copyright   Copyright (c) Ripen, LLC
 * @notice      The Postmark logo and name are trademarks of Wildbit, LLC
 * @license     http://www.opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Ripen\Postmark\Model\Transport;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Filesystem\Driver\File;
use Laminas\Http\Response;
use Laminas\Http\Client;
use Laminas\Http\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Header\HeaderInterface;
use Ripen\Postmark\Helper\Data;
use Ripen\Postmark\Model\Transport\Exception as PostmarkTransportException;
use Psr\Log\LogLevel;

/**
 * Postmark transport class
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * This class acts as a protocol adapter between Magento, Symfony Mailer,
 * and the Postmark API, which inherently requires multiple dependencies.
 */
class Postmark implements MailerInterface
{
    /**
     * Postmark API Uri
     */
    protected const string API_URI = 'https://api.postmarkapp.com/';

    /**
     * Limit of recipients per message in total.
     */
    protected const int RECIPIENTS_LIMIT = 20;

    /**
     * Postmark API key
     *
     * @var string|null
     */
    protected ?string $apiKey = null;

    /**
     * @var Data
     */
    protected Data $helper;

    /**
     * @var JsonSerializer
     */
    private JsonSerializer $jsonSerializer;

    /**
     * @var File
     */
    private File $fileDriver;

    /**
     * @param Data $helper
     * @param JsonSerializer $jsonSerializer
     * @param File $fileDriver
     */
    public function __construct(
        Data $helper,
        JsonSerializer $jsonSerializer,
        File $fileDriver
    ) {
        $this->helper = $helper;
        $this->jsonSerializer = $jsonSerializer;
        $this->fileDriver = $fileDriver;

        $apiKey = $this->helper->getApiKey();
        if (empty($apiKey)) {
            throw new PostmarkTransportException(__CLASS__ . ' requires API key');
        }
        $this->apiKey = $apiKey;
    }

    /**
     * Send request to Postmark service
     *
     * @param Email|RawMessage $message
     * @param Envelope|null $envelope
     * @return void
     * @throws PostmarkTransportException
     * @link https://postmarkapp.com/developer/user-guide/send-email-with-api
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function send(Email|RawMessage $message, ?Envelope $envelope = null): void
    {
        $recipients = $this->getRecipients($message);
        $bodyVersions = $this->getBody($message);

        $data = $recipients + [
            'From' => $this->getFrom($message),
            'Subject' => $this->getSubject($message),
            'ReplyTo' => $this->getReplyTo($message),
            'HtmlBody' => $bodyVersions['text/html'],
            'TextBody' => $bodyVersions['text/plain'],
            'Attachments' => $this->getAttachments($message),
            'Tag' => $this->getTags($message),
        ];

        $errorMessage = null;
        try {
            $response = $this->prepareHttpClient('/email')
                ->setMethod(Request::METHOD_POST)
                ->setRawBody($this->jsonSerializer->serialize($data))
                ->send();
            $this->parseResponse($response);
        } catch (PostmarkTransportException $e) {
            $errorMessage = $e->getMessage();
            throw $e;
        } finally {
            if ($this->helper->isDebugMode()) {
                $debugData = $this->jsonSerializer->serialize(
                    array_intersect_key(
                        $data,
                        array_flip(['From', 'Subject', 'ReplyTo', 'Tag'])
                    )
                );
                $debugStatus = $errorMessage ? "failed to send with error '$errorMessage'" : 'sent';
                $this->helper->log("Postmark email $debugStatus: $debugData", LogLevel::DEBUG);
            }
        }
    }

    /**
     * Get an HTTP client instance
     *
     * @param string $path
     * @return Client
     */
    protected function prepareHttpClient(string $path): Client
    {
        return $this->getHttpClient()->setUri(self::API_URI . $path);
    }

    /**
     * Returns a HTTP client object
     *
     * @return Client
     */
    public function getHttpClient(): Client
    {
        $client = new Client();
        $client->setHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Postmark-Server-Token' => $this->apiKey
        ]);

        return $client;
    }

    /**
     * Parse response object and check for errors
     *
     * @see https://postmarkapp.com/developer/api/overview#response-codes  (possible HTTP status codes)
     *
     * @param Response $response
     * @return array
     * @throws Exception
     */
    protected function parseResponse(Response $response): array
    {
        $result = $this->jsonSerializer->unserialize($response->getBody());

        if ($response->isClientError()) {

            $errorCode = $result['ErrorCode'] ?? 'Unknown';
            $errorMessage = $result['Message'] ?? 'Unknown';

            throw match ($response->getStatusCode()) {
                401 => new PostmarkTransportException(
                    'Postmark request error: Unauthorized - Missing or incorrect API Key header.'
                ),
                422 => new PostmarkTransportException(
                    sprintf(
                        'Postmark request error: Unprocessable Entity - API error code %s, message: %s',
                        $errorCode, $errorMessage
                    )
                ),
                500 => new PostmarkTransportException(
                    'Postmark request error: Postmark Internal Server Error'
                ),
                503 => new PostmarkTransportException(
                    'Postmark request error: Service Unavailable (planned service outage)'
                ),
                default => new PostmarkTransportException(
                    sprintf(
                        'Unknown error during request to Postmark server - API error code %s, message: %s',
                        $errorCode,
                        $errorMessage
                    )
                ),
            };
        }

        if (! is_array($result)) {
            throw new PostmarkTransportException('Unexpected value returned from server');
        }
        return $result;
    }

    /**
     * Get mail From
     *
     * @param Email $message
     * @return string|null
     */
    public function getFrom(Email $message): ?string
    {
        $sender = $message->getSender();
        if ($sender instanceof Address) {
            $name = $sender->getName();
            $address = $sender->getAddress();
        } else {
            $from = $message->getFrom();
            $name = null;
            $address = null;

            if (!empty($from) && $from[0] instanceof Address) {
                $name = $from[0]->getName();
                $address = $from[0]->getAddress();
            }
        }

        if (empty($address)) {
            throw new PostmarkTransportException('No from address specified');
        }

        return empty($name) ? $address : "$name <$address>";
    }

    /**
     * Get mail recipients (To, Cc, and Bcc)
     *
     * @param Email $message
     * @return array
     * @throws PostmarkTransportException
     */
    public function getRecipients(Email $message): array
    {
        $recipients = [
            'To' => $this->addressesToEmails($message->getTo()),
            'Cc' => $this->addressesToEmails($message->getCc()),
            'Bcc' => $this->addressesToEmails($message->getBcc()),
        ];

        $totalRecipients = array_sum(array_map('count', $recipients));

        if ($totalRecipients === 0) {
            throw new PostmarkTransportException(
                'Invalid email: must contain at least one of "To", "Cc", and "Bcc" headers'
            );
        }

        if ($totalRecipients > self::RECIPIENTS_LIMIT) {
            throw new PostmarkTransportException(
                'Exceeded Postmark recipients limit per message'
            );
        }

        return [
            'To' => implode(',', $recipients['To']),
            'Cc' => implode(',', $recipients['Cc']),
            'Bcc' => implode(',', $recipients['Bcc']),
        ];
    }

    /**
     * Convert Symfony Address[] to a simple email string array
     *
     * @param Address[] $addresses
     * @return string[]
     */
    private function addressesToEmails(array $addresses): array
    {
        $emails = [];
        foreach ($addresses as $address) {
            if ($address instanceof Address) {
                $emails[] = $address->getAddress();
            }
        }
        return $emails;
    }

    /**
     * Get mail Reply To
     *
     * @param Email $message
     * @return string
     */
    public function getReplyTo(Email $message): string
    {
        $addresses = $message->getReplyTo();

        $replyTo = [];
        foreach ($addresses as $address) {
            $replyTo[] = $address->getAddress();
        }

        return implode(',', $replyTo);
    }

    /**
     * Get mail subject
     *
     * @param Email $message
     * @return string
     */
    public function getSubject(Email $message): string
    {
        return $message->getSubject();
    }

    /**
     * Get mail body (HTML and plain text)
     *
     * @param Email $message
     * @return array ['text/html': string, 'text/plain': string ]
     * @throws PostmarkTransportException
     */
    public function getBody(Email $message): array
    {
        try {
            $bodyVersions = [
                'text/html' => (string) ($message->getHtmlBody() ?? ''),
                'text/plain' => (string) ($message->getTextBody() ?? ''),
            ];

            if ($bodyVersions['text/html'] === '' && $bodyVersions['text/plain'] === '') {
                $body = $message->getBody();
                $this->extractTextBodiesFromPart($body, $bodyVersions);
            }
        } catch (\LogicException $e) {
            throw new PostmarkTransportException('No body specified', 0, $e);
        }

        if ($bodyVersions['text/html'] === '' && $bodyVersions['text/plain'] === '') {
            throw new PostmarkTransportException('No body specified');
        }

        return $bodyVersions;
    }

    /**
     * Extract text bodies from a message part.
     *
     * @param AbstractPart $part
     * @param array $bodyVersions
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function extractTextBodiesFromPart(AbstractPart $part, array &$bodyVersions): void
    {
        if ($part instanceof TextPart) {
            $type = $part->getMediaType() . '/' . $part->getMediaSubtype();
            if ($type === 'text/html' && $bodyVersions['text/html'] === '') {
                $bodyVersions['text/html'] = (string) $part->getBody();
            } elseif ($type === 'text/plain' && $bodyVersions['text/plain'] === '') {
                $bodyVersions['text/plain'] = (string) $part->getBody();
            }
            return;
        }

        if ($part instanceof AbstractMultipartPart) {
            foreach ($part->getParts() as $child) {
                if ($child instanceof AbstractPart) {
                    $this->extractTextBodiesFromPart($child, $bodyVersions);
                }
                if ($bodyVersions['text/html'] !== '' && $bodyVersions['text/plain'] !== '') {
                    return;
                }
            }
        }
    }

    /**
     * Get mail Tag(s) from Postmark-Tag headers.
     *
     * @param Email $message
     * @return string
     */
    public function getTags(Email $message): string
    {
        $headers = $message->getHeaders();

        /** @var HeaderInterface[] $tagHeaders */
        $tagHeaders = $headers->all('Postmark-Tag');

        $tags = [];
        foreach ($tagHeaders as $tagHeader) {
            // Symfony headers expose the header value via getBodyAsString()
            $value = trim($tagHeader->getBodyAsString());
            if ($value !== '') {
                $tags[] = $value;
            }
        }

        return implode(',', $tags);
    }

    /**
     * Get mail Attachments
     *
     * @param Email $message
     * @return array<int, array{ContentType: string, Name: string, Content: string}>
     */
    public function getAttachments(Email $message): array
    {
        $body = $message->getBody();

        $attachments = [];
        $this->collectAttachmentsFromPart($body, $attachments);

        return $attachments;
    }

    /**
     * Collect attachments from a message part.
     *
     * @param AbstractPart $part
     * @param array $attachments
     * @return void
     */
    private function collectAttachmentsFromPart(AbstractPart $part, array &$attachments): void
    {
        if ($part instanceof DataPart) {
            $filename = (string) ($part->getFilename() ?? '');
            if ($filename === '') {
                // If there's no filename, it's often an inline/embedded part; skip to match old behavior.
                return;
            }

            $contentType = $part->getMediaType() . '/' . $part->getMediaSubtype();

            $raw = $part->getBody();
            if (is_resource($raw)) {
                $handle = $raw;

                try {
                    // phpcs:disable Magento2.Functions.DiscouragedFunction.Discouraged
                    $content = stream_get_contents($handle);
                    // phpcs:enable Magento2.Functions.DiscouragedFunction.Discouraged
                    $raw = ($content === false) ? '' : $content;
                } finally {
                    $this->fileDriver->fileClose($handle);
                }
            }

            $attachments[] = [
                'ContentType' => $contentType,
                'Name' => $filename,
                'Content' => base64_encode((string) $raw),
            ];
            return;
        }

        // Skip textual parts (handled separately by getBody()).
        if ($part instanceof TextPart) {
            return;
        }

        if ($part instanceof AbstractMultipartPart) {
            foreach ($part->getParts() as $child) {
                if ($child instanceof AbstractPart) {
                    $this->collectAttachmentsFromPart($child, $attachments);
                }
            }
        }
    }
}
