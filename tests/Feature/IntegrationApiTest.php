<?php

namespace Tests\Feature;

use Tests\TestCase;

class IntegrationApiTest extends TestCase
{
    public function test_discipline_rejects_when_key_not_configured(): void
    {
        config(['services.socialcredit.key' => null]);
        $this->getJson('/api/integration/discipline', ['X-Integration-Key' => ''])->assertStatus(401);
    }

    public function test_discipline_rejects_wrong_key(): void
    {
        config(['services.socialcredit.key' => 'benar']);
        $this->getJson('/api/integration/discipline')->assertStatus(401);
        $this->getJson('/api/integration/discipline', ['X-Integration-Key' => 'salah'])->assertStatus(401);
    }

    public function test_discipline_requires_valid_period(): void
    {
        config(['services.socialcredit.key' => 'benar']);
        $h = ['X-Integration-Key' => 'benar'];
        $this->getJson('/api/integration/discipline', $h)->assertStatus(422);
        $this->getJson('/api/integration/discipline?from=2026-05-01&to=2026-01-01', $h)->assertStatus(422);
    }
}
