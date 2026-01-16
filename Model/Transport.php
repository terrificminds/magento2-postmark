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

namespace Ripen\Postmark\Model;

use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Mail\Transport as MailTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Ripen\Postmark\Model\Transport\Postmark;
use Ripen\Postmark\Helper\Data;

class Transport extends MailTransport implements TransportInterface
{
    /**
     * @var EmailMessageInterface
     */
    protected EmailMessageInterface $message;

    /**
     * @var Data
     */
    protected Data $helper;

    /**
     * @var Postmark
     */
    protected Postmark $transportPostmark;

    /**
     * @param Data $helper
     * @param Postmark $transportPostmark
     * @param EmailMessageInterface $message
     */
    public function __construct(
        Data $helper,
        Postmark $transportPostmark,
        EmailMessageInterface $message,
    ) {
        $this->helper  = $helper;
        $this->transportPostmark = $transportPostmark;

        if ($this->helper->canUse()) {
            $this->message = $message;
        } else {
            parent::__construct($message);
        }
    }

    /**
     * Send mail using this transport
     *
     * @return void
     * @throws MailException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function sendMessage(): void
    {
        if (! $this->helper->canUse()) {
            parent::sendMessage();
            return;
        }

        try {
            $email = $this->createEmailFromMessage();
            $this->transportPostmark->send($email);
        } catch (\Exception $e) {
            throw new MailException(new Phrase($e->getMessage()), $e);
        }
    }

    /**
     * Create an Email object from the message
     *
     * @return Email
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function createEmailFromMessage(): Email
    {
        $email = new Email();

        $toLines = [];
        $ccLines = [];
        $bccLines = [];
        $fromLine = null;
        $subjectLine = null;

        foreach ((array) $this->message->getHeaders() as $line) {
            $line = trim((string) $line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $value = ltrim($value);

            if (strcasecmp($name, 'To') === 0) {
                $toLines[] = $value;
                continue;
            }
            if (strcasecmp($name, 'Cc') === 0) {
                $ccLines[] = $value;
                continue;
            }
            if (strcasecmp($name, 'Bcc') === 0) {
                $bccLines[] = $value;
                continue;
            }
            if ($fromLine === null && strcasecmp($name, 'From') === 0) {
                $fromLine = $value;
                continue;
            }
            if ($subjectLine === null && strcasecmp($name, 'Subject') === 0) {
                $subjectLine = $value;
                continue;
            }

            $email->getHeaders()->addTextHeader($name, $value);
        }

        if ($fromLine !== null) {
            $email->from(Address::create($fromLine));
        }

        $this->applyRecipients($email, $toLines, $ccLines, $bccLines);

        if ($subjectLine !== null) {
            $email->subject($subjectLine);
        }

        $email->setBody($this->message->getBody());

        return $email;
    }

    /**
     * Apply recipients to the email object
     *
     * @param Email $email
     * @param array $toLines
     * @param array $ccLines
     * @param array $bccLines
     * @return void
     */
    protected function applyRecipients(
        Email $email,
        array $toLines,
        array $ccLines,
        array $bccLines
    ): void {
        if ($toLines !== []) {
            $email->to(...$this->parseMailboxList($toLines));
        }
        if ($ccLines !== []) {
            $email->cc(...$this->parseMailboxList($ccLines));
        }
        if ($bccLines !== []) {
            $email->bcc(...$this->parseMailboxList($bccLines));
        }
    }

    /**
     * Get message
     *
     * @return EmailMessageInterface
     */
    public function getMessage(): EmailMessageInterface
    {
        return $this->message;
    }

    /**
     * Parse a list of mailboxes into an array of Address objects
     *
     * @param string[] $lines Header values (without the "Xxx:" prefix), e.g. ["a@a.com, b@b.com", "c@c.com"]
     * @return Address[]
     */
    private function parseMailboxList(array $lines): array
    {
        $addresses = [];

        foreach ($lines as $line) {
            foreach (explode(',', (string) $line) as $piece) {
                $piece = trim($piece);
                if ($piece === '') {
                    continue;
                }

                $addresses[] = Address::create($piece);
            }
        }

        return $addresses;
    }
}
