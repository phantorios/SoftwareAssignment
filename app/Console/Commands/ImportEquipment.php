<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Services\EquipmentFileParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ImportEquipment extends Command
{
    protected $signature = 'equipment:parse';

    protected $description = 'Parse SAP equipment export file and save to database';

    public function handle(EquipmentFileParser $parser)
    {
        $file = $this->getLatestEquipmentFile();

        if (!$file) {
            $this->warn('No EQUIPMENTS file found.');
            return Command::FAILURE;
        }

        $check = $parser->validate($file, 5); // allow max 5% record parse errors

        if (!$check['ok']) {
            Log::error('Equipment import aborted: corrupt file detected', [
                'file' => $file,
                'check' => $check,
            ]);

            $this->error('Corrupt file detected. Import aborted.');
            $this->error('Reason: ' . ($check['reason'] ?? 'Unknown'));

            // IMPORTANT: do not change DB, do not move file
            return Command::FAILURE;
        }

        // Only parse if validation passed
        $records = $parser->parse($file);

        $saved = 0;

        DB::transaction(function () use ($records, &$saved) {

            foreach ($records as $r) {

                if (empty($r['equipment'])) {
                    continue;
                }

                Equipment::updateOrCreate(

                    ['Equipment' => $r['equipment']],

                    [
                        'Material' => $r['material'] ?? null,

                        'MaterialWithoutFet' =>
                            str_replace('-FET','',$r['material'] ?? '') ?: 'UNKNOWN',

                        'Description' => $r['material_description'] ?? null,

                        'IH09Description' =>
                            $r['description_tech_object'] ?? null,

                        'Room' => $r['room'] ?? null,

                        'Plant' => $r['Plnt'] ?? null,

                        'Location' => $r['location'] ?? null,

                        'Sloc' => $r['sloc'] ?? null,

                        'SuperEq' =>
                            $r['superord_equipment'] ?? null,

                        'ManufactSerialNumber' =>
                            $r['manufact_serial_number'] ?? '',

                        'SerNo' =>
                            $r['serial_number'] ?? null,

                        'UserStatus' =>
                            $r['stat1'] ?? '',

                        'SystemStatus' =>
                            $r['stat2'] ?? '',

                        'Dimensions' =>
                            $r['size_dimensions'] ?? null,

                        'GrossWeight' =>
                            $this->parseWeight($r['gross_weight'] ?? null),

                        'workcenter' =>
                            $r['Work_ctr'] ?? '',


                        // REQUIRED NOT NULL DATABASE FIELDS

                        'ToolCompetence' => 'UNKNOWN',

                        'NEN3140Int' => 0,

                        'MaintInt' => 0,

                        'CalInt' => 0,

                        'CertInt' => 0,

                        'CtrlInt' => 0,

                        'current_status' => 'UNKNOWN',

                        'material_status' => 'UNKNOWN',

                        'CreatedBy' =>
                            $r['created_by'] ?? 'importer',

                        'ChangedBy' =>
                            $r['changed_by'] ?? 'importer',

                        'CreatedOn' =>
                            $this->parseDate($r['created_on'] ?? null),

                        'ChangedOn' =>
                            $this->parseDate($r['chngd_on'] ?? null),
                    ]
                );

                $saved++;
            }
        });

        $this->info("Parsed: " . count($records));
        $this->info("Saved: " . $saved);
        $this->moveToProcessedFolder($file);
        $this->info("File moved to processed folder.");

        return Command::SUCCESS;
    }


    private function getLatestEquipmentFile(): ?string
    {
        $path = storage_path('app/import');

        $files = collect(glob($path . '/EQUIPMENTS_*.txt'));

        return $files->isEmpty()
            ? null
            : $files->sortDesc()->first();
    }


    private function parseDate($date)
    {
        if (!$date) return null;

        try {
            return Carbon::createFromFormat(
                'd.m.Y',
                $date
            )->format('Y-m-d H:i:s');

        } catch (\Exception) {

            return null;
        }
    }


    private function parseWeight($weight)
    {
        if (!$weight) return null;

        $weight = str_replace(['KG',' '],'',$weight);

        $weight = str_replace('.','',$weight);

        $weight = str_replace(',','.',$weight);

        return is_numeric($weight)
            ? (float)$weight
            : null;
    }


    private function moveToProcessedFolder(string $filePath): void
    {
        $processedDir = storage_path('app/import/processed');

        if (!is_dir($processedDir)) {
            mkdir($processedDir, 0755, true);
        }

        $fileName = basename($filePath);

        $destination = $processedDir . '/' . $fileName;

        if (!rename($filePath, $destination)) {

            \Log::error('Failed to move processed file', [
                'source' => $filePath,
                'destination' => $destination,
            ]);

            throw new \RuntimeException('Failed to move processed file.');
        }

        \Log::info('File moved to processed folder', [
            'file' => $destination,
        ]);
    }
}
