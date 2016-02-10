#!php
<?php
/**
* @author SignpostMarv
*/

namespace SignpostMarv\VerbalExpressionsTests\Generator;

require_once(getcwd() . '/vendor/autoload.php');

use RuntimeException;

if (count($argv) < 2) {
    throw new RuntimeException('No output directory specified!');
} else {
    DynamicTestGenerator::CreateTests($argv[1]);
}
