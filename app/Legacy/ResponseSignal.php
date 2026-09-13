<?php

namespace App\Legacy;

use App\Services\SafeResponse;
use Illuminate\Http\JsonResponse;

/** Control flow for procedural handlers; never terminates Laravel's lifecycle. */
final class ResponseSignal extends \Error
{
    public function __construct(public readonly array $payload, public readonly int $status = 200)
    {
        parent::__construct('Legacy response');
    }

    public function response(): JsonResponse
    {
        return response()->json(SafeResponse::payload($this->payload, $this->status), $this->status);
    }
}
