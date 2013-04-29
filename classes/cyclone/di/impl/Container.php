<?php
namespace cyclone\di\impl;

use cyclone\FileSystem;
use cyclone\di\IContainer;

/**
 * @author Bence Erős <crystal@cyclonephp.org>
 * @package di
 */
class Container implements IContainer {

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

    private function load() {
        $dep_files = $this->_filesystem->list_files('deps/deps.php');
        foreach ($dep_files as $dep_file) {
            $container = $this;
            require $dep_file;
        }
    }

    /**
     * @param $key string
     * @param $wrapper callable
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

    public function publish($key, $value) {
        if ( ! (isset($this->_deps[$key]) || isset($this->_dep_wrappers[$key]))) {
            $this->_deps[$key] = $value;
        }
        return $this;
    }

    public function get($key) {
        if (isset($this->_deps[$key]))
            return $this->_deps[$key];
        if (isset($this->_dep_wrappers[$key])) {
            return $this->_deps[$key] = $this->_dep_wrappers[$key]($this);
        }
        throw new \InvalidArgumentException("dependency '{$key}' not found");
    }

}
