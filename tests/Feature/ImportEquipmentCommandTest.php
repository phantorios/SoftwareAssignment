<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportEquipmentCommandTest extends TestCase
{
    public function test_command_fails_on_corrupt_file_and_does_not_move_it(): void
    {
        // Ensure folders exist
        @mkdir(storage_path('app/import'), 0755, true);
        @mkdir(storage_path('app/import/processed'), 0755, true);

        $source = base_path('tests/Fixtures/equipment/corrupt.txt');
        $target = storage_path('app/import/EQUIPMENTS_99999999999999.txt');

        copy($source, $target);

        $this->artisan('equipment:parse')
            ->assertFailed();

        // File should still be there (not moved)
        $this->assertFileExists($target);
        $this->assertFileDoesNotExist(storage_path('app/import/processed/EQUIPMENTS_99999999999999.txt'));

        // cleanup
        @unlink($target);
    }
}
