<?php
namespace cyclone\di;

use cyclone\FileSystem;

/**
 * @author Bence Erős <crystal@cyclonephp.org>
 * @package di
 */
class Container {

    /**
     * The file system object which will be used to load the dependencies.
     *
     * @var \cyclone\FileSystem
     */
    private $_filesystem;

    /**
     * An array containing the already loaded dependencies
     *
     * dep. name => dependency pairs
     *
     * @var array
     */
    private $_deps = array();

    /**
     * An array containing the dependencies which have been already loaded from the file system but
     * have not been extracted from their wrapper callable.
     *
     * dep. name => callable pairs
     *
     * @var array
     */
    private $_dep_wrappers = array();

    /**
     * A <code> dependency key =&gt; array&lt;callable&gt; </code> pair list.
     *
     * @var array
     */
    private $_post_construct_callables = array();

    private $_environment;

    /**
     * A stack of dependencies currently under loading.
     *
     * Before the @c get() method extracts a dependency from its wrapper, it checks if <code>$key</code> is already on
     * the stack; if it is, then it throws a @c CircularDependencyException . Otherwise it pushes the according
     * <code>$key</code> onto the stack.
     *
     * This stack serves as a simple dependency loop detector.
     *
     * @var array
     */
    private $_get_stack = array();

    public function __construct(FileSystem $filesystem, $environment = NULL) {
        $this->_filesystem = $filesystem;
        $this->_environment = $environment;
        $this->load();
    }

    private function load_dep_files($rel_path) {
        $dep_files = $this->_filesystem->list_files($rel_path);
        $container = $this;
        foreach ($dep_files as $dep_file) {
            require $dep_file;
        }
    }

    private function load() {
        if ($this->_environment !== NULL) {
            $this->load_dep_files("deps/{$this->_environment}/default.php");
        }
        $this->load_dep_files('deps/default.php');
    }

    /**
     * Puts a wrapped value into the internal dependency registry. The value should be wrapped into an
     * appropriate callable (typically a lambda function) which returns the actual value when it is called.
     *
     * If the dependency (returned by <code>$wrapper</code>) has subsequent dependencies, then <code>$wrapper</code>
     * should have a <code>$container</code> parameter which will be <code>$this</code>, therefore it can be used
     * to load the subsequent dependencies.
     *
     * Simple example: @code
     * deps/default.php:
     * $container->provide('app.mysqli', function() {
     *      return new Mysqli('localhost', 'myusername', 'mypassword', 'mydatabase');
     * });
     *
     * (somewhere in the application)
     * ...
     * $mysqli = $container->get('app.mysqli'); // returns the Mysqli instance
     * @endcode
     *
     * Advanced example with subsequent dependencies: @code
     * deps/default.php:
     * $container->provide('app.mysqli', function($container) {
     *      return new Mysqli($container->get('app.mysqli.host'),
     *          $container->get('app.mysqli.username'),
     *          $container->get('app.mysqli.password'),
     *          $container->get('app.mysqli.database')
     *      );
     * })->publish('app.mysqli.host', 'localhost')
     *  ->publish('app.mysqli.username', 'myusername')
     *  ->publish('app.mysqli.password', 'mypassword')
     *  ->publish('app.mysqli.database', 'mydatabase');
     *
     * (somewhere in the application)
     * ...
     * $mysqli = $container->get('app.mysqli'); // returns the Mysqli instance
     * @endcode
     *
     * If a dependency has previously been published or provided with the same <code>$key</code> then this method
     * does not do anything.
     *
     * @param $key string
     * @param $wrapper callable
     * @return Container
     * @throws \InvalidArgumentException if $wrapper is not a callable
     */
    public function provide($key, $wrapper) {
        if ( ! is_callable($wrapper))
            throw new \InvalidArgumentException('$wrapper must be a callable');
        if ( ! (isset($this->_deps[$key]) || isset($this->_dep_wrappers[$key]))) {
            $this->_dep_wrappers[$key] = $wrapper;
        }
        return $this;
    }

    /**
     * Puts a value into the internal dependency registry.
     *
     * If a dependency has previously been published or provided with the same <code>$key</code> then this method
     * does not do anything.
     *
     * @param $key the key which identifies the dependency
     * @param $value
     * @return Container
     */
    public function publish($key, $value) {
        if ( ! (isset($this->_deps[$key]) || isset($this->_dep_wrappers[$key]))) {
            $this->_deps[$key] = $value;
        }
        return $this;
    }

    /**
     * Returns an object which has been configured using @c publish() or @c provide() previously.
     *
     * @param $key string the name of the dependency which has been used to publish/provide the dependency
     * @return mixed the loaded dependency
     * @throws \InvalidArgumentException if no dependency has been found with <code>$key</code>
     * @throws CircularDependencyException if a dependency loop has been detected during building the dependency
     */
    public function get($key) {
        if (isset($this->_deps[$key]))
            return $this->_deps[$key];
        if (isset($this->_dep_wrappers[$key])) {
            if (isset($this->_get_stack[$key])) {
                $msg = 'Dependency loop detected: ';
                $dep_chain = array();
                foreach ($this->_get_stack as $dep => $dummy) {
                    if ($dep === $key || ! empty($dep_chain)) {
                        $dep_chain []= $dep;
                    }
                }
                $dep_chain []= $key;
                $msg .= implode(' -> ', $dep_chain);
                throw new CircularDependencyException($msg);
            }

            $this->_get_stack[$key] = TRUE;
            $rval = $this->_deps[$key] = $this->_dep_wrappers[$key]($this);
            if (isset($this->_post_construct_callables[$key])) {
                foreach($this->_post_construct_callables[$key] as $post_construct_cb) {
                    $post_construct_cb($rval, $this);
                }
            }
            unset($this->_get_stack[$key]);
            return $rval;
        }
        throw new \InvalidArgumentException("dependency '{$key}' not found");
    }

    public function post_construct($key, $callable) {
        if ( ! isset($this->_post_construct_callables[$key])) {
            $this->_post_construct_callables[$key] = array();
        }
        $this->_post_construct_callables[$key] []= $callable;
    }

}
