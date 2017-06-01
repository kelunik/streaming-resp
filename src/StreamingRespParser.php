<?php

namespace Kelunik\StreamingResp;

use Amp\ByteStream\OutputStream;
use Amp\ByteStream\Parser;
use Amp\Promise;

class StreamingRespParser implements OutputStream {
    private $parser;
    private $onResponse;

    public function __construct(callable $onResponse) {
        $this->onResponse = $onResponse;
        $this->parser = new Parser($this->parse());
    }

    private function parse(): \Generator {
        while (true) {
            $value = yield from $this->parseSingle();
            ($this->onResponse)($value);
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
                $length = (int) \substr($firstLine, 1, -2);

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

    /** @inheritdoc */
    public function write(string $data): Promise {
        return $this->parser->write($data);
    }

    /** @inheritdoc */
    public function end(string $finalData = ""): Promise {
        return $this->parser->end($finalData);
    }
}