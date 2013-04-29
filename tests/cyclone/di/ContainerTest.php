<?php
namespace cyclone\di;

/**
 * @author Bence Erős <crystal@cyclonephp.org>
 */
class ContainerTest extends \PHPUnit_Framework_TestCase {

    /**
     * @return Container
     */
    private function get_container() {
        $fs = $this->getMockBuilder('cyclone\\FileSystem')
            ->disableOriginalConstructor()->getMock();
        $fs->expects($this->once())
            ->method('list_files')
            ->with('deps/default.php')->will($this->returnValue(array()));
        return new Container($fs);
    }

    public function test_provide() {
        $container = $this->get_container();
        $obj = new \stdClass;
        $called = FALSE;
        $container->provide('key', function() use ($obj, &$called) {
            $called = TRUE;
            return $obj;
        });
        $this->assertFalse($called);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_get() {
        $container = $this->get_container();
        $obj = new \stdClass;
        $called = FALSE;
        $self = $this;
        $this->assertEquals($container, $container->provide('key', function($param_container) use ($obj, &$called, $container, $self) {
            $called = TRUE;
            $self->assertSame($param_container, $container);
            return $obj;
        }));
        $this->assertFalse($called);
        $this->assertSame($obj, $container->get('key'));
        $this->assertEquals(TRUE, $called);
        $called = FALSE;
        $container->get('key');
        $this->assertFalse($called);
        $container->get('nonexistent');
    }

    public function test_load() {
        $fs = $this->getMockBuilder('cyclone\\FileSystem')
            ->disableOriginalConstructor()->getMock();
        $fs->expects($this->once())
            ->method('list_files')
            ->with('deps/default.php')->will($this->returnValue(array(
                __DIR__ . '/dep1.php',
                __DIR__ . '/dep2.php'
            )));
        $container = new Container($fs);
        $this->assertEquals('key1-dep1', $container->get('key1'));
        $this->assertEquals('key2-dep1', $container->get('key2'));
        $this->assertEquals('key3-dep2', $container->get('key3'));
    }

    public function test_publish() {
        $container = $this->get_container();
        $this->assertEquals($container, $container->publish('key', 'val'));
        $this->assertEquals('val', $container->get('key'));
    }

    public function test_provide_override() {
        $container = $this->get_container();
        $provider1 = function() {
            return 1;
        };
        $provider2 = function() {
            return 2;
        };
        $container->provide('key1', $provider1);
        $container->provide('key1', $provider2);
        $this->assertEquals(1, $container->get('key1'));

        $container->publish('key2', 1);
        $container->publish('key2', 2);
        $this->assertEquals(1, $container->get('key2'));

        $container->publish('key3', 1);
        $container->provide('key3', $provider2);
        $this->assertEquals(1, $container->get('key3'));

        $container->provide('key4', $provider1);
        $container->publish('key4', 2);
        $this->assertEquals(1, $container->get('key4'));
    }

    public function test_environment_load() {
        $fs = $this->getMockBuilder('cyclone\\FileSystem')
            ->disableOriginalConstructor()->getMock();
        $fs->expects($this->at(0))
            ->method('list_files')
            ->with('deps/env/default.php')->will($this->returnValue(array(
                __DIR__ . '/env-dep1.php',
                __DIR__ . '/env-dep2.php'
            )));

        $fs->expects($this->at(1))
            ->method('list_files')
            ->with('deps/default.php')->will($this->returnValue(array(
                __DIR__ . '/dep1.php',
                __DIR__ . '/dep2.php'
            )));

        $container = new Container($fs, 'env');
        $this->assertEquals('key1-env1', $container->get('key1'));
        $this->assertEquals('key2-env2', $container->get('key2'));
        $this->assertEquals('key3-dep2', $container->get('key3'));
    }

    public function test_singleton() {
        $container = $this->get_container();
        $container->provide('key1', function() {
            return new \stdClass;
        });
        $obj = $container->get('key1');
        $this->assertSame($obj, $container->get('key1'));
    }

}
