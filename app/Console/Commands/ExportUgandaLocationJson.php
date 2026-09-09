<?php

namespace App\Console\Commands;

use App\Models\UgandaLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportUgandaLocationJson extends Command
{
    protected $signature = 'locations:export-json';

    protected $description = 'Export Uganda location hierarchy from MySQL into the JSON files the controller expects.';

    public function handle(): int
    {
        $base = storage_path('app/public/ugandaData');
        File::ensureDirectoryExists($base);

        $locations = UgandaLocation::query()
            ->where('is_active', true)
            ->orderBy('district')->orderBy('county')->orderBy('subcounty')->orderBy('parish')->orderBy('village')
            ->get();

        $districts = collect($locations)
            ->pluck('district')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $district) => ['id' => $district, 'name' => $district])
            ->all();

        $counties = collect($locations)
            ->map(fn ($row) => [
                'id' => (string) $row->county,
                'name' => (string) $row->county,
                'district' => (string) $row->district,
            ])
            ->unique(fn (array $row) => $row['district'].'|'.$row['id'])
            ->sortBy('name')
            ->values()
            ->all();

        $subcounties = collect($locations)
            ->map(fn ($row) => [
                'id' => (string) $row->subcounty,
                'name' => (string) $row->subcounty,
                'county' => (string) $row->county,
            ])
            ->unique(fn (array $row) => $row['county'].'|'.$row['id'])
            ->sortBy('name')
            ->values()
            ->all();

        $parishes = collect($locations)
            ->map(fn ($row) => [
                'id' => (string) $row->parish,
                'name' => (string) $row->parish,
                'subcounty' => (string) $row->subcounty,
            ])
            ->unique(fn (array $row) => $row['subcounty'].'|'.$row['id'])
            ->sortBy('name')
            ->values()
            ->all();

        $villages = collect($locations)
            ->map(fn ($row) => [
                'id' => (string) $row->village,
                'name' => (string) $row->village,
                'parish' => (string) $row->parish,
            ])
            ->unique(fn (array $row) => $row['parish'].'|'.$row['id'])
            ->sortBy('name')
            ->values()
            ->all();

        File::put($base.'/districts.json', json_encode($districts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($base.'/counties.json', json_encode($counties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($base.'/sub_counties.json', json_encode($subcounties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($base.'/parishes.json', json_encode($parishes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($base.'/villages.json', json_encode($villages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Exported Uganda locations to JSON files.');

        return self::SUCCESS;
    }
}
