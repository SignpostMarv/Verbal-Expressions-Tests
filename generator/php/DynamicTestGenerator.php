<?php
/**
* @author SignpostMarv
*/

namespace SignpostMarv\VerbalExpressionsTests\Generator;

use CallbackFilterIterator;
use DirectoryIterator;
use InvalidArgumentException;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use PhpParser\BuilderFactory;
use PhpParser\PrettyPrinter\Standard as StandardPrettyPrinter;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Const_;
use RuntimeException;
use SplFileInfo;

class DynamicTestGenerator
{
    public static function GetTestFiles()
    {
        $dir = dirname(dirname(__DIR__));

        $retriever = new UriRetriever;
        $schema = $retriever->retrieve(
            'file://' . realpath($dir . '/schema/tests.json')
        );

        return new CallbackFilterIterator(
            new DirectoryIterator($dir . '/tests'),
            function (SplFileInfo $file) use ($schema) {
                $validator = new Validator;
                return (
                    $file->isFile() &&
                    $file->isReadable() &&
                    preg_match('/\.json$/', $file->getBasename()) &&
                    $validator->check(
                        $schema,
                        json_decode(file_get_contents($file->getRealPath()))
                    ) == null &&
                    $validator->isValid()
                );
            }
        );
    }

    private static function CreateArg($callStackItemArg)
    {
        $arg = null;
        switch (gettype($callStackItemArg)) {
            case 'boolean':
                $arg = new Arg(
                    new ConstFetch(
                        new Name($callStackItemArg ? 'true' : 'false')
                    )
                );
            break;
            case 'string':
                $arg = new Arg(
                    new String_($callStackItemArg)
                );
            break;
            case 'integer':
                $arg = new Arg(
                    new LNumber($callStackItemArg)
                );
            break;
            case 'float':
                $arg = new Arg(
                    new LNumber($callStackItemArg)
                );
            break;
        }
        if (is_null($arg)) {
            throw new RuntimeException(
                'Could not determine argument type!'
            );
        }
        return $arg;
    }

    public static function CreateTests($dir=null)
    {
        if (!is_dir($dir)) {
            throw new InvalidArgumentException(
                'Output directory "' . $dir . '" does not exist!'
            );
        }
        $factory = new BuilderFactory();
        $prettyPrinter = new StandardPrettyPrinter;
        $lazySanitizer = function ($in) {
            return preg_replace('/[^a-z]/i', '', ucfirst($in));
        };
        $namespaceTests = 'VerbalExpressions\PHPVerbalExpressions\Tests';
        $useVerbExp = (
            'VerbalExpressions\PHPVerbalExpressions\VerbalExpressions'
        );
        $variableOut = new Variable('out');
        $variableRegex = new Variable('regex');
        $variableThis = new Variable('this');
        $nameVerbalExpressions = new Name('VerbalExpressions');
        $constVerbExp = new ClassConstFetch(
            new Name('static'),
            'VerbExpClassName'
        );
        $nameAssertInstanceOf = new Name('assertInstanceOf');
        $nameAssertEquals = new Name('assertEquals');
        $assignNewVerbExpToRegex = new Assign(
            $variableRegex,
            new New_($nameVerbalExpressions)
        );
        $parseCallStackItem = function (
            $callStackItem,
            $method,
            $containsDesc
        ) use (
            $variableOut,
            $variableRegex,
            $variableThis,
            $nameAssertInstanceOf,
            $nameAssertEquals,
            $constVerbExp
        ) {
            $method->setDocComment(
                '/**
                * ' . $containsDesc->description . '
                */'
            );
            if (count($callStackItem->arguments) > 0) {
                $methodCall = new MethodCall(
                    $variableRegex,
                    new Name($callStackItem->method),
                    array_map(
                        array('static', 'CreateArg'),
                        $callStackItem->arguments
                    )
                );
            } else {
                $methodCall = new MethodCall(
                    $variableRegex,
                    new Name($callStackItem->method)
                );
            }
            $method->addStmt(
                new Assign($variableOut, $methodCall)
            );
            if ($callStackItem->returnType === 'sameInstance') {
                $method->addStmt(
                    new MethodCall(
                        $variableThis,
                        $nameAssertInstanceOf,
                        array(
                            $constVerbExp,
                            $variableOut
                        )
                    )
                );
                $method->addStmt(
                    new Assign($variableRegex, $variableOut)
                );
            } else {
                $method->addStmt(
                    new MethodCall(
                        $variableThis,
                        $nameAssertEquals,
                        array(
                            new String_($callStackItem->returnType),
                            new FuncCall(
                                new Name('gettype'),
                                array(
                                    $variableOut
                                )
                            )
                        )
                    )
                );
            }
        };
        foreach (static::GetTestFiles() as $testFile) {
            $data = json_decode(file_get_contents($testFile->getRealPath()));
            $className = (
                $lazySanitizer(
                    substr($testFile->getBasename(), 0, -4)
                ) .
                'DynamicallyGeneratedTest'
            );
            $class = $factory->class(
                $className
            )->extend(
                'PHPUnit_Framework_TestCase'
            )->addStmt(
                new ClassConst(
                    array(
                        new Const_(
                            'VerbExpClassName',
                            new String_($useVerbExp)
                        ),
                    )
                )
            );

            if (isset($data->patterns)) {
                foreach ($data->patterns as $pattern) {
                    $method = $factory->method(
                        'pattern' . $lazySanitizer($pattern->name)
                    )->makeProtected()->addStmt($assignNewVerbExpToRegex);
                    foreach ($pattern->callStack as $callStackItem) {
                        $parseCallStackItem($callStackItem, $method, $pattern);
                    }
                    $method->addStmt(
                        new Return_($variableRegex)
                    );
                    $class->addStmt($method);
                }
            }
            $methods = array();
            $methodIncrements = array();
            foreach ($data->tests as $test) {
                $expectedOutputValue = $test->output->default;
                if (isset($test->output->php)) {
                    $expectedOutputValue = $test->output->php;
                }
                $methodName = 'test' . $lazySanitizer($test->name);
                if (in_array($methodName, $methods)) {
                    if (!isset($methodIncrements[$methodName])) {
                        $methodIncrements[$methodName] = 1;
                    }
                    $methodName .= ++$methodIncrements[$methodName];
                }
                $methods[] = $methodName;
                $method = $factory->method($methodName)->makePublic();
                if (isset($test->pattern)) {
                    $method->addStmt(
                        new Assign(
                            $variableRegex,
                            new StaticCall(
                                new Name('static'),
                                'pattern' . $lazySanitizer($test->pattern)
                            )
                        )
                    );
                    $method->addStmt(
                        new MethodCall(
                            $variableThis,
                            $nameAssertInstanceOf,
                            array(
                                $constVerbExp,
                                $variableRegex
                            )
                        )
                    );
                } else {
                    $method->addStmt($assignNewVerbExpToRegex);
                }
                foreach ($test->callStack as $callStackItem) {
                    $parseCallStackItem($callStackItem, $method, $test);
                }
                $method->addStmt(
                    new MethodCall(
                        $variableThis,
                        $nameAssertEquals,
                        array(
                            static::CreateArg($expectedOutputValue),
                            $variableOut
                        )
                    )
                );
                $class->addStmt(
                    $method
                );
            }
            file_put_contents(
                ($dir . '/' . $className . '.php'),
                $prettyPrinter->prettyPrintFile(
                    array(
                        $factory->namespace(
                            $namespaceTests
                        )->addStmt(
                            $factory->use('PHPUnit_Framework_TestCase')
                        )->addStmt(
                            $factory->use($useVerbExp)
                        )->addStmt($class)->getNode()
                    )
                )
            );
        }
    }
}
