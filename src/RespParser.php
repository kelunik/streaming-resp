<?php

namespace Kelunik\StreamingResp;

interface RespParser {
    public function push(string $data);
}