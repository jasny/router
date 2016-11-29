<?php

namespace Jasny\Router;

use PHPUnit_Framework_MockObject_Matcher_Invocation as Invocation;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

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
     * @return MockObject
     */
    protected function createCallbackMock(Invocation $matcher, $with = [], $return = null)
    {
        $callback = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $callback->expects($matcher)->method('__invoke')
            ->with(...$with)
            ->willReturn($return);
        
        return $callback;
    }
    
    /**
     * Assert a non-fatal error
     * 
     * @param int    $type
     * @param string $message
     */
    protected function assertLastError($type, $message)
    {
        $error = error_get_last();
        
        $expect = compact('type', 'message');
        
        if (is_array($error)) {
            $error = array_intersect_key($error, $expect);
        }
        
        $this->assertEquals($expect, $error);
    }
}
