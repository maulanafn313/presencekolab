<?php

namespace Tests\Unit;

use App\Services\StartupDirectory;
use DomainException;
use PHPUnit\Framework\TestCase;

class StartupDirectoryTest extends TestCase
{
    public function test_spelling_variants_share_one_key(): void
    {
        $this->assertSame('uniinside', StartupDirectory::key('Uni Inside'));
        $this->assertSame('uniinside', StartupDirectory::key('Uni-Inside'));
        $this->assertSame('uniinside', StartupDirectory::key(' UNIINSIDE '));
        $this->assertSame('', StartupDirectory::key(' - '));
    }

    public function test_exact_names_are_counted_and_variants_need_cleanup(): void
    {
        $result = StartupDirectory::summarize($this->startups(), ['Uni Inside', 'Uni-Inside', 'UniInside', 'ngolab', 'Ngolab']);

        $this->assertSame(1, $result['startups'][0]['members']);
        $this->assertSame(1, $result['startups'][1]['members']);
        $this->assertSame([
            ['name' => 'ngolab', 'members' => 1, 'suggested_id' => 2],
            ['name' => 'Uni-Inside', 'members' => 1, 'suggested_id' => 1],
            ['name' => 'UniInside', 'members' => 1, 'suggested_id' => 1],
        ], $result['unregistered']);
    }

    public function test_agreed_alias_is_suggested_and_unknown_names_are_not_guessed(): void
    {
        $unregistered = StartupDirectory::summarize($this->startups(), ['Ngolab express', 'Tim Baru'])['unregistered'];

        $this->assertSame(2, $unregistered[0]['suggested_id']);
        $this->assertNull($unregistered[1]['suggested_id']);
    }

    public function test_inactive_startup_is_never_suggested(): void
    {
        $startups = $this->startups();
        $startups[0]['is_active'] = 0;

        $this->assertNull(StartupDirectory::summarize($startups, ['Uni-Inside'])['unregistered'][0]['suggested_id']);
    }

    public function test_portal_payload_is_cleaned(): void
    {
        $this->assertSame([['id' => 'a1', 'name' => 'Uni Inside']], StartupDirectory::parsePortal([['id' => 'a1', 'name' => ' Uni   <b>Inside</b> ', 'logo' => 'x']]));
    }

    /** @dataProvider invalidPortalPayloads */
    public function test_invalid_portal_payload_changes_nothing(mixed $payload): void
    {
        $this->expectException(DomainException::class);
        StartupDirectory::parsePortal($payload);
    }

    public static function invalidPortalPayloads(): array
    {
        return [
            'empty' => [[]],
            'error object' => [['error' => 'down']],
            'missing name' => [[['id' => 'a1']]],
            'blank name' => [[['id' => 'a1', 'name' => ' - ']]],
            'not json' => [null],
        ];
    }

    private function startups(): array
    {
        return [
            ['id' => 1, 'name' => 'Uni Inside', 'portal_id' => 'p1', 'is_active' => 1],
            ['id' => 2, 'name' => 'Ngolab', 'portal_id' => null, 'is_active' => 1],
        ];
    }
}
