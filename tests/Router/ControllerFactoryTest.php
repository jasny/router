<?php

namespace Jasny\Router;

use Jasny\Router\ControllerFactory;
use Jasny\TestHelper;

/**
 * @covers Jasny\Router\ControllerFactory
 */
class ControllerFactoryTest extends \PHPUnit_Framework_TestCase
{
    use TestHelper;
    
    public function testInvoke()
    {
        $mockController = new \stdClass();
        
        $factory = $this->createPartialMock(ControllerFactory::class, ['instantiate', 'assertClass']);
        $factory->expects($this->once())->method('instantiate')->with('FooController')->willReturn($mockController);
        
        $result = $factory('foo');
        
        $this->assertSame($mockController, $result);
    }
    
    public function invalidClassProvider()
    {
        return [
            [
                null,
                'foo-bar-zoo',
                "Can't route to controller 'FooBarZooController': class not exists"
            ],
            [
                null,
                ['foo', 'BAR', 'zoo'],
                "Can't route to controller 'Foo\Bar\ZooController': class not exists"
            ],
            [
                'StDclass',
                'foo',
                "Can't route to controller 'StDclass': case mismatch with 'stdClass'"
            ],
            [
                null,
                'fooBarZoo',
                "Can't route to controller 'FoobarzooController': class not exists"
            ],
            [
                null,
                '-foo-bar-zoo',
                "Can't route to controller '-fooBarZooController': invalid classname"
            ],
            [
                null,
                'foo--bar-zoo',
                "Can't route to controller 'Foo--barZooController': invalid classname"
            ],
            [
                null,
                'Foo\Bar\zoo',
                "Can't route to controller 'Foo\\\\bar\\\\zooController': invalid classname"
            ]
        ];
    }
    
    /**
     * @dataProvider invalidClassProvider
     * 
     * @param string       $class
     * @param string|array $controller
     * @param string       $message
     */
    public function testAssertClass($class, $controller, $message)
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($message);
        
        if (empty($class)) {
            $runner = $this->createPartialMock(ControllerFactory::class, ['instantiate']);
        } else {
            $runner = $this->createPartialMock(ControllerFactory::class, ['instantiate', 'getClass']);
            $runner->expects($this->once())->method('getClass')->with('foo')->willReturn($class);
        }
        $runner->expects($this->never())->method('instantiate');
        
        $runner($controller);
    }
    
    public function testInstantiate()
    {
        $runner = $this->createPartialMock(ControllerFactory::class, ['getClass']);
        $runner->expects($this->once())->method('getClass')->with('foo')->willReturn('stdClass');
        
        $result = $runner('foo');
        
        $this->assertInstanceOf(\stdClass::class, $result);
    }
    
    
    public function testChain()
    {
        $mockController = new \stdClass();
        $chain = $this->createCallbackMock($this->once(), ['FooController'], $mockController);
        
        $factory = $this->getMockBuilder(ControllerFactory::class)
            ->setConstructorArgs([$chain])
            ->setMethods(['assertClass'])
            ->getMock();
        
        $result = $factory('foo');
        
        $this->assertSame($mockController, $result);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidChain()
    {
        new ControllerFactory('not callable');
    }
}
