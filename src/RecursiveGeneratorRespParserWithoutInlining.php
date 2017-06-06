<?php

namespace Kelunik\StreamingResp;

use Amp\Parser\Parser;

class RecursiveGeneratorRespParserWithoutInlining extends Parser implements RespParser {
    private $onResponse;

    public function __construct(callable $onResponse) {
        $this->onResponse = $onResponse;
        parent::__construct($this->parse());
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

                while ($arraySize-- > 0) {
                    $value = yield from $this->parseSingle();
                    $values[] = $value;
                }

                return $values;

            case "$":
                $length = (int) $value;

                if ($length === -1) {
                    $payload = null;
                } else {
                    $payload = yield ($length + 2);
                    $payload = \substr($payload, 0, -2);
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
