<?php

namespace App\Console\Commands;

use App\Models\UgandaLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportUgandaLocationJson extends Command
{
    protected $signature = 'locations:import-json';

    protected $description = 'Import Uganda location hierarchy from the JSON files into the MySQL UgandaLocation table.';

    public function handle(): int
    {
        $base = storage_path('app/public/ugandaData');
        if (! File::exists($base.'/districts.json')) {
            $this->error('Missing districts.json under storage/app/public/ugandaData');
            return self::FAILURE;
        }

        $districts = json_decode(File::get($base.'/districts.json'), true) ?: [];
        $counties = json_decode(File::get($base.'/counties.json'), true) ?: [];
        $subcounties = json_decode(File::get($base.'/sub_counties.json'), true) ?: [];
        $parishes = json_decode(File::get($base.'/parishes.json'), true) ?: [];
        $villages = json_decode(File::get($base.'/villages.json'), true) ?: [];

        UgandaLocation::query()->update(['is_active' => false]);

        foreach ($villages as $village) {
            $parish = collect($parishes)->firstWhere('id', $village['parish']) ?? collect($parishes)->firstWhere('name', $village['parish']);
            $subcounty = collect($subcounties)->firstWhere('id', $parish['subcounty'] ?? null) ?? collect($subcounties)->firstWhere('name', $parish['subcounty'] ?? null);
            $county = collect($counties)->firstWhere('id', $subcounty['county'] ?? null) ?? collect($counties)->firstWhere('name', $subcounty['county'] ?? null);
            $district = collect($districts)->firstWhere('id', $county['district'] ?? null) ?? collect($districts)->firstWhere('name', $county['district'] ?? null);

            UgandaLocation::query()->updateOrCreate([
                'district' => $district['name'] ?? ($district['id'] ?? ''),
                'county' => $county['name'] ?? ($county['id'] ?? ''),
                'subcounty' => $subcounty['name'] ?? ($subcounty['id'] ?? ''),
                'parish' => $parish['name'] ?? ($parish['id'] ?? ''),
                'village' => $village['name'] ?? ($village['id'] ?? ''),
            ], [
                'is_active' => true,
            ]);
        }

        $this->info('Imported Uganda locations from JSON files into the UgandaLocation table.');

        return self::SUCCESS;
    }
}
