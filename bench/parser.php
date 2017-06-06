<?php

use Kelunik\StreamingResp\IterativeRespParser;
use Kelunik\StreamingResp\RecursiveGeneratorRespParser;
use Kelunik\StreamingResp\RespParser;

chdir(__DIR__);
error_reporting(E_ALL);

require "../vendor/autoload.php";

class BenchCase {
    private $parser;

    public function __construct(RespParser $parser) {
        $this->parser = $parser;
    }

    public function benchSimpleArray() {
        $start = microtime(true);

        for ($i = 0; $i < 1000000; $i++) {
            $this->parser->push("*2\r\n$5\r\nHello\r\n:123456789\r\n");
        }

        return microtime(1) - $start;
    }

    public function benchSimpleString() {
        $start = microtime(true);

        for ($i = 0; $i < 1000000; $i++) {
            $this->parser->push("+Hello\r\n");
        }

        return microtime(1) - $start;
    }

    public function benchBulkString() {
        $start = microtime(true);

        for ($i = 0; $i < 1000000; $i++) {
            $this->parser->push("$5\r\nHello\r\n");
        }

        return microtime(1) - $start;
    }
}

$test = new BenchCase(new IterativeRespParser(function () {}));
printf("%s: %f @ %s\n", IterativeRespParser::class, $test->benchSimpleString(), "simple-string");
printf("%s: %f @ %s\n", IterativeRespParser::class, $test->benchBulkString(), "bulk-string");
printf("%s: %f @ %s\n", IterativeRespParser::class, $test->benchSimpleArray(), "simple-array");

$test = new BenchCase(new RecursiveGeneratorRespParser(function () {}));
printf("%s: %f @ %s\n", RecursiveGeneratorRespParser::class, $test->benchSimpleString(), "simple-string");
printf("%s: %f @ %s\n", RecursiveGeneratorRespParser::class, $test->benchBulkString(), "bulk-string");
printf("%s: %f @ %s\n", RecursiveGeneratorRespParser::class, $test->benchSimpleArray(), "simple-array");