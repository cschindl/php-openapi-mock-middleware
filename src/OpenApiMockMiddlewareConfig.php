<?php

declare(strict_types=1);

namespace Cschindl\OpenAPIMock;

use Vural\OpenAPIFaker\Options;

class OpenApiMockMiddlewareConfig
{
    public function __construct(
        private bool $validateRequest = false,
        private bool $validateResponse = false,
        private Options|null $options = null,
    ) {
    }

    public function validateRequest(): bool
    {
        return $this->validateRequest;
    }

    public function validateResponse(): bool
    {
        return $this->validateResponse;
    }

    public function getOptions(): Options
    {
        return $this->options ?? new Options();
    }
}
