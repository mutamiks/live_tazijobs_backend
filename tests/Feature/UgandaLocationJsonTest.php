<?php

namespace Tests\Feature;

use Tests\TestCase;

class UgandaLocationJsonTest extends TestCase
{
    public function test_uganda_locations_are_loaded_from_storage_json_and_cascaded(): void
    {
        $districtResponse = $this->getJson('/api/locations/uganda?district=Kampala')
            ->assertOk();

        $this->assertContains('Kampala', $districtResponse->json('data.districts'));
        $this->assertContains('Kampala Central Division', $districtResponse->json('data.counties'));
        $this->assertNotContains('Maruzi County', $districtResponse->json('data.counties'));

        $countyResponse = $this->getJson('/api/locations/uganda?district=Kampala&county=Kampala%20Central%20Division')
            ->assertOk();

        $this->assertContains('Kampala Central', $countyResponse->json('data.subcounties'));
    }
}
