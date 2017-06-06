<?php

namespace Kelunik\StreamingResp;

use Amp\Parser\Parser;

class RecursiveRespParser extends Parser implements RespParser {
    private $onResponse;

    public function __construct(callable $onResponse) {
        $this->onResponse = $onResponse;
        parent::__construct($this->parse());
    }

    private function parse(): \Generator {
        while (true) {
            // parseSingle() is inlined here for improved speed
            $firstLine = yield "\r\n";
            \assert(\strlen($firstLine) > 0);

            $type = $firstLine[0];
            $value = \substr($firstLine, 1);

            switch ($type) {
                case "+":
                    ($this->onResponse)($value);
                    break;

                case ":":
                    ($this->onResponse)((int) $value);
                    break;

                case "*":
                    $arraySize = (int) $value;
                    $values = [];

                    while (--$arraySize > 0) {
                        $values[] = yield from $this->parseSingle();
                    }

                    ($this->onResponse)($values);
                    break;

                case "$":
                    $length = (int) $value;

                    if ($length === -1) {
                        ($this->onResponse)(null);
                    } else {
                        $payload = yield ($length + 2);
                        ($this->onResponse)(\substr($payload, 0, -2));
                    }

                    break;

                case "-":
                    ($this->onResponse)(new RespError($value));
                    break;

                default:
                    throw new ParseException(\sprintf(
                        "Unknown RESP data type: %s",
                        $type
                    ));
            }
        }
    }

    private function parseSingle() {
        $firstLine = yield "\r\n";
        \assert(\strlen($firstLine) > 0);

        $type = $firstLine[0];
        $value = \substr($firstLine, 1);

        switch ($type) {
            case "+":
                return $value;

            case ":":
                return (int) $value;

            case "*":
                $arraySize = (int) $value;
                $values = [];

                while (--$arraySize > 0) {
                    $values[] = yield from $this->parseSingle();
                }

                return $values;

            case "$":
                $length = (int) $value;

                if ($length === -1) {
                    $payload = null;
                } else {
                    $payload = \substr(yield ($length + 2), 0, -2);
                }

                return $payload;

            case "-":
                return new RespError($value);

            default:
                throw new ParseException(\sprintf(
                    "Unknown RESP data type: %s",
                    $type
                ));
        }
    }
}