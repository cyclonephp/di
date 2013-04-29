<?php

$container->provide('key1', function() {
    return 'key1-dep1';
})->publish('key2', 'key2-dep1');