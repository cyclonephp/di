<?php

$container->provide('key1', function() {
    return 'key1-dep2';
})->publish('key2', 'key2-dep2')
->publish('key3', 'key3-dep2');