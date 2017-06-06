<?php

namespace Kelunik\StreamingResp;

use Amp\Parser\Parser;

class IterativeGeneratorRespParser extends Parser implements RespParser {
    private $onResponse;

    public function __construct(callable $onResponse) {
        $this->onResponse = $onResponse;
        parent::__construct($this->parse());
    }

    private function parse(): \Generator {
        $value = null;

        $currentResponse = null;
        $arrayStack = [];
        $currentSize = 0;
        $arraySizes = [];

        while (true) {
            $firstLine = yield "\r\n";
            \assert(\strlen($firstLine) > 0);

            $type = $firstLine[0];
            $value = \substr($firstLine, 1);

            switch ($type) {
                case "+":
                    break;

                case ":":
                    $value = (int) $value;
                    break;

                case "*":
                    $value = (int) $value;
                    break;

                case "$":
                    $length = (int) $value;

                    if ($length === -1) {
                        $value = null;
                    } else {
                        $value = yield ($length + 2);
                        $value = \substr($value, 0, -2);
                    }

                    break;

                case "-":
                    $value = new RespError($value);
                    break;

                default:
                    throw new ParseException(\sprintf(
                        "Unknown RESP data type: %s",
                        $type
                    ));
            }

            if ($currentResponse === null) {
                if ($type === "*") {
                    if ($value > 0) {
                        $currentSize = $value;
                        $arrayStack = $arraySizes = $currentResponse = [];

                        continue;
                    } else if ($value === 0) {
                        $value = [];
                    } else {
                        $value = null;
                    }
                }

                ($this->onResponse)($value);
            } else { // extend array response
                if ($type === "*") {
                    if ($value > 0) {
                        $arraySizes[] = $currentSize;
                        $arrayStack[] = &$currentResponse;
                        $currentSize = $value + 1;
                        $currentResponse[] = [];
                        $currentResponse = &$currentResponse[\count($currentResponse) - 1];
                    } else if ($value === 0) {
                        $currentResponse[] = [];
                    } else {
                        $currentResponse[] = null;
                    }
                } else {
                    $currentResponse[] = $value;
                }

                while (--$currentSize === 0) {
                    if (!$arrayStack) {
                        ($this->onResponse)($currentResponse);
                        $currentResponse = null;
                        break;
                    }

                    // index doesn't start at 0 :(
                    end($arrayStack);
                    $key = key($arrayStack);
                    $currentResponse = &$arrayStack[$key];
                    $currentSize = array_pop($arraySizes);
                    unset($arrayStack[$key]);
                }
            }
        }
    }
}