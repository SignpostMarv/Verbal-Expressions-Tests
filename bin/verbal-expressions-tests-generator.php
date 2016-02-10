#!php
<?php
/**
* @author SignpostMarv
*/

namespace SignpostMarv\VerbalExpressionsTests\Generator;

require_once(dirname(__DIR__) . '/vendor/autoload.php');

if (!class_exists(__NAMESPACE__ . '\\DynamicTestGenerator')) {
    require_once(dirname(__DIR__) . '/generator/php/DynamicTestGenerator.php');
}

use RuntimeException;

if (count($argv) < 2) {
    throw new RuntimeException('No output directory specified!');
} else {
    DynamicTestGenerator::CreateTests($argv[1]);
}
