<?php

namespace Kelunik\StreamingResp;

class IterativeRespParser implements RespParser {
    const CRLF = "\r\n";
    const TYPE_SIMPLE_STRING = "+";
    const TYPE_ERROR = "-";
    const TYPE_ARRAY = "*";
    const TYPE_BULK_STRING = "$";
    const TYPE_INTEGER = ":";

    private $responseCallback;
    private $buffer = "";
    private $currentResponse = null;
    private $arrayStack;
    private $currentSize;
    private $arraySizes;

    public function __construct(callable $responseCallback) {
        $this->responseCallback = $responseCallback;
    }

    public function push(string $data) {
        $this->buffer .= $data;

        do {
            $type = $this->buffer[0];
            $pos = strpos($this->buffer, self::CRLF);

            if ($pos === false) {
                return;
            }

            switch ($type) {
                case self::TYPE_SIMPLE_STRING:
                case self::TYPE_INTEGER:
                case self::TYPE_ARRAY:
                case self::TYPE_ERROR:
                    $payload = substr($this->buffer, 1, $pos - 1);
                    $remove = $pos + 2;
                    break;

                case self::TYPE_BULK_STRING:
                    $length = (int) substr($this->buffer, 1, $pos);

                    if ($length === -1) {
                        $payload = null;
                        $remove = $pos + 2;
                    } else {
                        if (strlen($this->buffer) < $pos + $length + 4) {
                            return;
                        }

                        $payload = substr($this->buffer, $pos + 2, $length);
                        $remove = $pos + $length + 4;
                    }

                    break;

                default:
                    throw new ParseException(\sprintf(
                        "Unknown RESP data type: %s",
                        $type
                    ));
            }

            $this->buffer = substr($this->buffer, $remove);

            switch ($type) {
                case self::TYPE_INTEGER:
                case self::TYPE_ARRAY:
                    $payload = intval($payload);
                    break;

                case self::TYPE_ERROR:
                    $payload = new RespError($payload);
                    break;

                default:
                    break;
            }

            if ($this->currentResponse !== null) { // extend array response
                if ($type === self::TYPE_ARRAY) {
                    if ($payload >= 0) {
                        $this->arraySizes[] = $this->currentSize;
                        $this->arrayStack[] = &$this->currentResponse;
                        $this->currentSize = $payload + 1;
                        $this->currentResponse[] = [];
                        $this->currentResponse = &$this->currentResponse[sizeof($this->currentResponse) - 1];
                    } else {
                        $this->currentResponse[] = null;
                    }
                } else {
                    $this->currentResponse[] = $payload;
                }

                while (--$this->currentSize === 0) {
                    if (sizeof($this->arrayStack) === 0) {
                        $cb = $this->responseCallback;
                        $cb($this->currentResponse);
                        $this->currentResponse = null;
                        break;
                    }

                    // index doesn't start at 0 :(
                    end($this->arrayStack);
                    $key = key($this->arrayStack);
                    $this->currentResponse = &$this->arrayStack[$key];
                    $this->currentSize = array_pop($this->arraySizes);
                    unset($this->arrayStack[$key]);
                }
            } else if ($type === self::TYPE_ARRAY) { // start new array response
                if ($payload > 0) {
                    $this->currentSize = $payload;
                    $this->arrayStack = $this->arraySizes = $this->currentResponse = [];
                } else if ($payload === 0) {
                    $cb = $this->responseCallback;
                    $cb([]);
                } else {
                    $cb = $this->responseCallback;
                    $cb(null);
                }
            } else { // single data type response
                $cb = $this->responseCallback;
                $cb($payload);
            }
        } while (isset($this->buffer[0]));
    }
}