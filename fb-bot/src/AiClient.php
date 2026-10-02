<?php

declare(strict_types=1);

namespace App;

interface AiClient
{
    /**
     * One request: cached system prompt + one user message, answer constrained
     * to the given JSON schema.
     *
     * @param array<string, mixed> $schema
     * @throws AiException
     */
    public function complete(string $system, string $user, array $schema, string $model, ?string $effort): AiResponse;
}
