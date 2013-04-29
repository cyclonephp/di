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

    private $_environment;

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
     * @param $key the name of the dependency which has been used to publish/provide the dependency
     * @return mixed the loaded dependency
     * @throws \InvalidArgumentException if no dependency has been found with <code>$key</code>
     */
    public function get($key) {
        if (isset($this->_deps[$key]))
            return $this->_deps[$key];
        if (isset($this->_dep_wrappers[$key])) {
            return $this->_deps[$key] = $this->_dep_wrappers[$key]($this);
        }
        throw new \InvalidArgumentException("dependency '{$key}' not found");
    }

}
