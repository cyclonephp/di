<?php

$container->provide('key1', function() {
    return 'key1-env2';
})->provide('key2', function() {
    return 'key2-env2';
});