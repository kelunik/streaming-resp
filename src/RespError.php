<?php

namespace Kelunik\StreamingResp;

class RespError {
    private $message;

    public function __construct(string $message) {
        $this->message = $message;
    }

    public function getMessage(): string {
        return $this->message;
    }
}