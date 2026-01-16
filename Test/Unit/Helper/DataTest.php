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

namespace Ripen\Postmark\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Ripen\Postmark\Helper\Data;

class DataTest extends TestCase
{
    /**
     * @var MockObject|ScopeConfigInterface
     */
    private ScopeConfigInterface|MockObject $scopeConfig;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $logger;

    /**
     * @var Context|MockObject
     */
    private Context|MockObject $context;

    /**
     * @var Data
     */
    private Data $helper;

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->context = $this->createMock(Context::class);
        $this->context->method('getScopeConfig')->willReturn($this->scopeConfig);

        $this->helper = new Data($this->context, $this->logger);
    }

    /**
     * @return void
     */
    public function testIsEnabledReturnsBoolFromConfig(): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('getValue')
            ->with('postmark/settings/enabled', 'store')
            ->willReturn('1');

        self::assertTrue($this->helper->isEnabled());
    }

    /**
     * @return void
     */
    public function testGetApiKeyReturnsStringOrNull(): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('getValue')
            ->with('postmark/settings/apikey', 'store')
            ->willReturn('abc123');

        self::assertSame('abc123', $this->helper->getApiKey());
    }

    /**
     * @return void
     */
    public function testIsDebugModeReturnsBoolFromConfig(): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('getValue')
            ->with('postmark/settings/debug_mode', 'store')
            ->willReturn(0);

        self::assertFalse($this->helper->isDebugMode());
    }

    /**
     * @dataProvider canUseDataProvider
     */
    public function testCanUse(bool $enabled, ?string $apiKey, bool $expected): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path) use ($enabled, $apiKey) {
                return match ($path) {
                    'postmark/settings/enabled' => $enabled ? '1' : '0',
                    'postmark/settings/apikey' => $apiKey,
                    default => null,
                };
            }
        );

        self::assertSame($expected, $this->helper->canUse());
    }

    /**
     * @return array[]
     */
    public static function canUseDataProvider(): array
    {
        return [
            'disabled + key' => [false, 'abc123', false],
            'enabled + no key' => [true, null, false],
            'enabled + empty key' => [true, '', false],
            'enabled + key' => [true, 'abc123', true],
        ];
    }

    /**
     * @return void
     */
    public function testLogDelegatesToLogger(): void
    {
        $this->logger->expects(self::once())
            ->method('log')
            ->with(LogLevel::ERROR, 'test message');

        $this->helper->log('test message', LogLevel::ERROR);
    }
}
