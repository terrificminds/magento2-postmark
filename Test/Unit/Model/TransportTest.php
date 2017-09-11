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
 * to info@sumoheavy.com so we can send you a copy immediately.
 *
 * @category    SUMOHeavy
 * @package     SUMOHeavy_Postmark
 * @copyright   Copyright (c) SUMO Heavy Industries, LLC
 * @notice      The Postmark logo and name are trademarks of Wildbit, LLC
 * @license     http://www.opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
namespace SUMOHeavy\Postmark\Test\Unit\Model;

class TransportTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $_helper;

    /**
     * @var \SUMOHeavy\Postmark\Model\Transport
     */
    private $_transport;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $_transportPostmarkMock;

    public function __construct()
    {
        $this->_helper = $this->getMock(
            '\SUMOHeavy\Postmark\Helper\Data',
            [],
            [],
            '',
            false
        );

        $this->_message = $this->getMock('\Magento\Framework\Mail\Message');

        $this->_transportPostmarkMock = $this->getMock(
            '\SUMOHeavy\Postmark\Model\Transport\Postmark',
            [],
            [$this->_helper],
            '',
            false
        );
        $this->_transport = new \SUMOHeavy\Postmark\Model\Transport($this->_message, $this->_transportPostmarkMock, $this->_helper);
    }

    public function testSendMessage()
    {
        $this->_helper->expects($this->once())
            ->method('canUse')
            ->will($this->returnValue(true));

        $this->_transportPostmarkMock->expects($this->once())
            ->method('send')
            ->will($this->returnValue(null));

        $this->_transport->sendMessage();
    }

    public function testSendMessageException()
    {
        $this->_helper->expects($this->once())
            ->method('canUse')
            ->will($this->returnValue(true));

        $this->_transportPostmarkMock->expects($this->once())
            ->method('send')
            ->will($this->throwException(new \SUMOHeavy\Postmark\Model\Transport\Exception('test')));

        try {
            $this->_transport->sendMessage();
            $this->fail('Exception not thrown');
        } catch(\Exception $e) {
            $this->assertEquals('test', $e->getMessage());
        }
    }
}
