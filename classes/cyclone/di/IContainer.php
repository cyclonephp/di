<?php
namespace cyclone\di;
/**
 * @author Bence Erős <crystal@cyclonephp.org>
 */
interface IContainer {

    public function provide($key, $wrapper);

    public function publish($key, $value);

    public function get($key);

}