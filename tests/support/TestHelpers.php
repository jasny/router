<?php

namespace Jasny\Router;

use PHPUnit_Framework_MockObject_Matcher_Invocation as Invocation;

/**
 * Helper methods for PHPUnit tests
 */
trait TestHelpers
{
    /**
     * Create mock for next callback
     * 
     * @param Invocation  $matcher
     * @param array       $with     With arguments
     * @param mixed       $return
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function createCallbackMock(Invocation $matcher, $with = [], $return = null)
    {
        $callback = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $callback->expects($matcher)->method('__invoke')
            ->with(...$with)
            ->willReturn($return);
        
        return $callback;
    }
}
