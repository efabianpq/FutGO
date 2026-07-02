<?php

namespace App\Services\Support;

class TicketClassifierService
{
    public function classify(string $message, array $history = []): array
    {
        return app(SupportAIGateway::class)->classify($message, $history);
    }
}
