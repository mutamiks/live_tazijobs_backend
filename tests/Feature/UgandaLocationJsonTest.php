<?php

namespace Tests\Feature;

use App\Models\UgandaLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UgandaLocationJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_uganda_locations_fall_back_to_database_when_location_json_files_are_missing(): void
    {
        UgandaLocation::query()->create([
            'district' => 'Kampala',
            'county' => 'Kampala Central Division',
            'subcounty' => 'Kampala Central',
            'parish' => 'Nakasero I',
            'village' => 'NAKASERO',
            'is_active' => true,
        ]);

        $districtResponse = $this->getJson('/api/locations/uganda?district=Kampala')
            ->assertOk();

        $this->assertContains('Kampala', $districtResponse->json('data.districts'));
        $this->assertContains('Kampala Central', $districtResponse->json('data.counties'));

        $subcountyResponse = $this->getJson('/api/locations/uganda?district=Kampala&county=Kampala%20Central&subcounty=Central')
            ->assertOk();

        $this->assertContains('Central', $subcountyResponse->json('data.subcounties'));
        $this->assertContains('Nakasero', $subcountyResponse->json('data.parishes'));
    }

    public function test_location_json_export_command_generates_the_expected_file_contract(): void
    {
        UgandaLocation::query()->create([
            'district' => 'Kampala',
            'county' => 'Kampala Central Division',
            'subcounty' => 'Kampala Central',
            'parish' => 'Nakasero I',
            'village' => 'NAKASERO',
            'is_active' => true,
        ]);

        $this->artisan('locations:export-json')->assertSuccessful();

        $districts = json_decode(File::get(storage_path('app/public/ugandaData/districts.json')), true);
        $counties = json_decode(File::get(storage_path('app/public/ugandaData/counties.json')), true);
        $subcounties = json_decode(File::get(storage_path('app/public/ugandaData/sub_counties.json')), true);
        $parishes = json_decode(File::get(storage_path('app/public/ugandaData/parishes.json')), true);
        $villages = json_decode(File::get(storage_path('app/public/ugandaData/villages.json')), true);

        $this->assertNotEmpty($districts);
        $this->assertContains(['id' => 'Kampala', 'name' => 'Kampala'], $districts);
        $this->assertContains(['id' => 'Kampala Central Division', 'name' => 'Kampala Central Division', 'district' => 'Kampala'], $counties);
        $this->assertContains(['id' => 'Kampala Central', 'name' => 'Kampala Central', 'county' => 'Kampala Central Division'], $subcounties);
        $this->assertContains(['id' => 'Nakasero I', 'name' => 'Nakasero I', 'subcounty' => 'Kampala Central'], $parishes);
        $this->assertContains(['id' => 'NAKASERO', 'name' => 'NAKASERO', 'parish' => 'Nakasero I'], $villages);
    }

    public function test_location_json_import_command_rebuilds_the_database_from_import_files(): void
    {
        File::ensureDirectoryExists(storage_path('app/public/ugandaData'));

        File::put(storage_path('app/public/ugandaData/districts.json'), json_encode([
            ['id' => 'Kampala', 'name' => 'Kampala'],
        ]));
        File::put(storage_path('app/public/ugandaData/counties.json'), json_encode([
            ['id' => 'Kampala Central', 'name' => 'Kampala Central', 'district' => 'Kampala'],
        ]));
        File::put(storage_path('app/public/ugandaData/sub_counties.json'), json_encode([
            ['id' => 'Central', 'name' => 'Central', 'county' => 'Kampala Central'],
        ]));
        File::put(storage_path('app/public/ugandaData/parishes.json'), json_encode([
            ['id' => 'Nakasero', 'name' => 'Nakasero', 'subcounty' => 'Central'],
        ]));
        File::put(storage_path('app/public/ugandaData/villages.json'), json_encode([
            ['id' => 'Nakasero I', 'name' => 'Nakasero I', 'parish' => 'Nakasero'],
        ]));

        $this->artisan('locations:import-json')->assertSuccessful();

        $this->assertDatabaseHas('uganda_locations', [
            'district' => 'Kampala',
            'county' => 'Kampala Central',
            'subcounty' => 'Central',
            'parish' => 'Nakasero',
            'village' => 'Nakasero I',
            'is_active' => true,
        ]);
    }
}
