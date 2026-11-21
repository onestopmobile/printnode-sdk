<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Exceptions;

use Saloon\Exceptions\Request\FatalRequestException;

/**
 * PrintNode could not be reached, or the connection broke before an answer came back. It is not
 * known whether PrintNode received the request: send a print job again with the same idempotency
 * key, so it cannot print twice.
 */
final class ConnectionException extends PrintNodeException
{
    public static function from(FatalRequestException $fatalRequestException): self
    {
        return new self(
            'Could not reach PrintNode: '.$fatalRequestException->getMessage(),
            0,
            $fatalRequestException,
        );
    }
}
