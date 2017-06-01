# streaming-resp

A streaming RESP parser for Amp.

## Installation

```
composer require kelunik/streaming-resp
```

## Usage

```php
$parser = new StreamingRespParser($inputStream);

while (yield $parser->advance()) {
    $parsedItem = $parser->getCurrent();
}
```