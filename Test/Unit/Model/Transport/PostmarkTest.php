<?php

declare(strict_types=1);

namespace Ripen\Postmark\Test\Unit\Model\Transport;

use Laminas\Http\Response;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ripen\Postmark\Helper\Data;
use Ripen\Postmark\Model\Transport\Exception as PostmarkTransportException;
use Ripen\Postmark\Model\Transport\Postmark;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\TextPart;

class PostmarkTest extends TestCase
{
    /**
     * @var Data|MockObject
     */
    private Data $helperMock;

    /**
     * @var Json|MockObject
     */
    private Json $jsonSerializerMock;

    /**
     * @var File|MockObject
     */
    private File $fileDriverMock;

    /**
     * @var Postmark
     */
    private Postmark $postmark;

    protected function setUp(): void
    {
        $this->helperMock = $this->createMock(Data::class);
        $this->jsonSerializerMock = $this->createMock(Json::class);
        $this->fileDriverMock = $this->createMock(File::class);

        $this->helperMock
            ->method('getApiKey')
            ->willReturn('test-api-key');

        $this->helperMock
            ->method('isDebugMode')
            ->willReturn(false);

        $this->postmark = new Postmark(
            $this->helperMock,
            $this->jsonSerializerMock,
            $this->fileDriverMock
        );
    }

    public function testConstructorThrowsExceptionWhenApiKeyIsMissing(): void
    {
        $this->expectException(PostmarkTransportException::class);

        $helperMock = $this->createMock(Data::class);
        $helperMock->method('getApiKey')->willReturn(null);

        new Postmark(
            $helperMock,
            $this->jsonSerializerMock,
            $this->fileDriverMock
        );
    }

    public function testGetFromUsesSenderAddress(): void
    {
        $email = new Email();
        $email->sender(new Address('sender@example.com', 'Sender'));

        $this->assertEquals(
            'Sender <sender@example.com>',
            $this->postmark->getFrom($email)
        );
    }

    public function testGetFromThrowsExceptionWhenMissing(): void
    {
        $this->expectException(PostmarkTransportException::class);

        $email = new Email();
        $this->postmark->getFrom($email);
    }

    public function testGetRecipientsReturnsCommaSeparatedAddresses(): void
    {
        $email = new Email();
        $email->to('a@example.com')
            ->cc('b@example.com')
            ->bcc('c@example.com');

        $recipients = $this->postmark->getRecipients($email);

        $this->assertEquals('a@example.com', $recipients['To']);
        $this->assertEquals('b@example.com', $recipients['Cc']);
        $this->assertEquals('c@example.com', $recipients['Bcc']);
    }

    public function testGetRecipientsThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(PostmarkTransportException::class);

        $email = new Email();
        $this->postmark->getRecipients($email);
    }

    public function testGetBodyReturnsHtmlAndText(): void
    {
        $email = new Email();
        $email->html('<p>Hello</p>');
        $email->text('Hello');

        $body = $this->postmark->getBody($email);

        $this->assertEquals('<p>Hello</p>', $body['text/html']);
        $this->assertEquals('Hello', $body['text/plain']);
    }

    public function testGetBodyExtractsFromMultipart(): void
    {
        $email = new Email();
        $email->setBody(
            new TextPart('Plain text body', 'utf-8', 'plain')
        );

        $body = $this->postmark->getBody($email);

        $this->assertEquals('', $body['text/html']);
        $this->assertEquals('Plain text body', $body['text/plain']);
    }

    public function testGetBodyThrowsExceptionWhenMissing(): void
    {
        $this->expectException(PostmarkTransportException::class);

        $email = new Email();
        $this->postmark->getBody($email);
    }

    public function testGetTagsReturnsCommaSeparatedValues(): void
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('Postmark-Tag', 'order');
        $email->getHeaders()->addTextHeader('Postmark-Tag', 'invoice');

        $this->assertEquals(
            'order,invoice',
            $this->postmark->getTags($email)
        );
    }

    public function testGetAttachmentsReturnsEncodedAttachment(): void
    {
        $email = new Email();

        $attachment = new DataPart(
            'file-content',
            'test.txt',
            'text/plain'
        );

        $email->setBody($attachment);

        $attachments = $this->postmark->getAttachments($email);

        $this->assertCount(1, $attachments);
        $this->assertEquals('test.txt', $attachments[0]['Name']);
        $this->assertEquals(
            base64_encode('file-content'),
            $attachments[0]['Content']
        );
    }

    public function testParseResponseThrowsExceptionOnClientError(): void
    {
        $this->expectException(PostmarkTransportException::class);

        $response = new Response();
        $response->setStatusCode(401);
        $response->setContent(
            json_encode(['Message' => 'Unauthorized'])
        );

        $this->jsonSerializerMock
            ->method('unserialize')
            ->willReturn(['Message' => 'Unauthorized']);

        $reflection = new \ReflectionMethod(Postmark::class, 'parseResponse');
        $reflection->setAccessible(true);
        $reflection->invoke($this->postmark, $response);
    }
}
