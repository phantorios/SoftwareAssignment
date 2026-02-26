<?php

namespace Tests\Unit;

use App\Services\EquipmentFileParser;
use Tests\TestCase;

class EquipmentFileParserTest extends TestCase
{
    public function test_validate_returns_ok_for_valid_fixture(): void
    {
        $parser = new EquipmentFileParser();

        $file = base_path('tests/Fixtures/equipment/valid.txt');

        $result = $parser->validate($file, 5);

        $this->assertTrue($result['ok'], $result['reason'] ?? 'Expected ok=true');
        $this->assertGreaterThanOrEqual(1, $result['total_records'] ?? 0);
    }

    public function test_validate_fails_for_corrupt_fixture(): void
    {
        $parser = new EquipmentFileParser();

        $file = base_path('tests/Fixtures/equipment/corrupt.txt');

        $result = $parser->validate($file, 5);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['reason'] ?? null);
    }

    public function test_parse_returns_records_with_expected_keys(): void
    {
        $parser = new EquipmentFileParser();
        $file = base_path('tests/Fixtures/equipment/valid.txt');

        $records = $parser->parse($file);

        $this->assertNotEmpty($records);
        $this->assertArrayHasKey('equipment', $records[0]);
        $this->assertArrayHasKey('material', $records[0]);
        $this->assertArrayHasKey('material_description', $records[0]);
        $this->assertArrayHasKey('room', $records[0]);
    }
}
