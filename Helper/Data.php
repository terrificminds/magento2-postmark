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

namespace Ripen\Postmark\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Data extends AbstractHelper
{
    protected const string XML_PATH_ENABLED = 'postmark/settings/enabled';

    protected const string XML_PATH_DEBUG_MODE = 'postmark/settings/debug_mode';

    protected const string XML_PATH_APIKEY = 'postmark/settings/apikey';

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @param Context $context
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->logger = $logger;
    }

    /**
     * Returns whether the module is enabled or not
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Returns the API key
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_APIKEY,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Returns whether the module is in debug mode or not
     *
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_DEBUG_MODE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Returns whether the module can be used or not
     *
     * @return boolean
     */
    public function canUse(): bool
    {
        return $this->isEnabled() && $this->getApiKey();
    }

    /**
     * Logs a message
     *
     * @param string $msg
     * @param string $level
     * @return void
     */
    public function log(string $msg, string $level = LogLevel::INFO): void
    {
        $this->logger->log($level, $msg);
    }
}
