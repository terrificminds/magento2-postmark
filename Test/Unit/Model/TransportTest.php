<?php

declare(strict_types=1);

namespace Ripen\Postmark\Test\Unit\Model;

use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\EmailMessageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ripen\Postmark\Helper\Data;
use Ripen\Postmark\Model\Transport;
use Ripen\Postmark\Model\Transport\Postmark;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\TextPart;

class TransportTest extends TestCase
{
    /**
     * @var Data|MockObject
     */
    private Data $helperMock;

    /**
     * @var Postmark|MockObject
     */
    private Postmark $postmarkMock;

    /**
     * @var EmailMessageInterface|MockObject
     */
    private EmailMessageInterface $messageMock;

    protected function setUp(): void
    {
        $this->helperMock = $this->createMock(Data::class);
        $this->postmarkMock = $this->createMock(Postmark::class);
        $this->messageMock = $this->createMock(EmailMessageInterface::class);
    }

    public function testGetMessageReturnsOriginalMessageWhenPostmarkEnabled(): void
    {
        $this->helperMock
            ->method('canUse')
            ->willReturn(true);

        $transport = new Transport(
            $this->helperMock,
            $this->postmarkMock,
            $this->messageMock
        );

        $this->assertSame(
            $this->messageMock,
            $transport->getMessage()
        );
    }

    public function testSendMessageUsesPostmarkWhenEnabled(): void
    {
        $this->helperMock
            ->method('canUse')
            ->willReturn(true);

        $this->messageMock
            ->method('getHeaders')
            ->willReturn([
                'From: Sender <sender@example.com>',
                'To: to@example.com',
                'Subject: Test subject',
            ]);

        $this->messageMock
            ->method('getBody')
            ->willReturn(new TextPart('Email body'));

        $this->postmarkMock
            ->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $transport = new Transport(
            $this->helperMock,
            $this->postmarkMock,
            $this->messageMock
        );

        $transport->sendMessage();
    }

    public function testSendMessageWrapsExceptionIntoMailException(): void
    {
        $this->helperMock
            ->method('canUse')
            ->willReturn(true);

        $this->messageMock
            ->method('getHeaders')
            ->willReturn([
                'From: sender@example.com',
                'To: to@example.com',
            ]);

        $this->messageMock
            ->method('getBody')
            ->willReturn(new TextPart('Body'));

        $this->postmarkMock
            ->method('send')
            ->willThrowException(new \RuntimeException('Postmark failed'));

        $transport = new Transport(
            $this->helperMock,
            $this->postmarkMock,
            $this->messageMock
        );

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Postmark failed');

        $transport->sendMessage();
    }
}
