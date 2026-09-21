<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCatalogRequest;
use App\Http\Requests\UpdateJobCategoryRequest;
use App\Models\JobCategory;
use App\Models\Language;
use App\Models\Religion;
use App\Models\UgandaLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class CatalogController extends Controller
{
    private const UGANDA_DISTRICTS = [
        'Abim', 'Adjumani', 'Agago', 'Alebtong', 'Amolatar', 'Amudat', 'Amuria', 'Amuru',
        'Apac', 'Arua', 'Budaka', 'Bududa', 'Bugiri', 'Bugweri', 'Buhweju', 'Buikwe',
        'Bukedea', 'Bukomansimbi', 'Bukwo', 'Bulambuli', 'Buliisa', 'Bundibugyo', 'Bushenyi',
        'Busia', 'Butaleja', 'Butebo', 'Buvuma', 'Buyende', 'Dokolo', 'Gomba', 'Gulu',
        'Hoima', 'Ibanda', 'Iganga', 'Isingiro', 'Jinja', 'Kaabong', 'Kabale', 'Kabarole',
        'Kaberamaido', 'Kalangala', 'Kaliro', 'Kalungu', 'Kamuli', 'Kamwenge', 'Kanungu',
        'Kapchorwa', 'Kasanda', 'Kasese', 'Katakwi', 'Kayunga', 'Kazo', 'Kibaale', 'Kiboga',
        'Kibuku', 'Kiruhura', 'Kiryandongo', 'Koboko', 'Kole', 'Kotido', 'Kumi', 'Kwania',
        'Kween', 'Kyankwanzi', 'Kyegegwa', 'Kyenjojo', 'Kyotera', 'Lamwo', 'Lira', 'Luuka',
        'Luwero', 'Lwengo', 'Lyantonde', 'Madi-Okollo', 'Manafwa', 'Maracha', 'Mbale',
        'Mbarara', 'Mitooma', 'Mityana', 'Moroto', 'Moyo', 'Mpigi', 'Mubende', 'Mukono',
        'Nabilatuk', 'Nakapiripirit', 'Nakaseke', 'Nakasongola', 'Namayingo', 'Namiumba',
        'Napak', 'Nebbi', 'Ngora', 'Ntoroko', 'Ntungamo', 'Nwoya', 'Obongi', 'Omoro',
        'Otuke', 'Oyam', 'Pader', 'Pallisa', 'Rakai', 'Rukiga', 'Rukungiri', 'Rushenyi',
        'Serere', 'Sheema', 'Sironko', 'Soroti', 'Tororo', 'Wakiso', 'Yumbe', 'Zombo',
    ];

    public function index()
    {
        return response()->json([
            'data' => [
                'job_categories' => JobCategory::query()->where('is_active', true)->orderBy('name')->get(),
                'languages' => Language::query()->where('is_active', true)->orderBy('name')->get(),
                'religions' => Religion::query()
                    ->where('is_active', true)
                    ->whereRaw('LOWER(name) != ?', ['christian'])
                    ->orderBy('name')
                    ->get(),
            ],
        ]);
    }

    public function adminIndex()
    {
        return response()->json([
            'data' => [
                'job_categories' => JobCategory::query()->orderBy('name')->get(),
                'languages' => Language::query()->orderBy('name')->get(),
                'religions' => Religion::query()
                    ->whereRaw('LOWER(name) != ?', ['christian'])
                    ->orderBy('name')
                    ->get(),
            ],
        ]);
    }

    public function storeJobCategory(StoreCatalogRequest $request)
    {
        return $this->storeCatalog(JobCategory::class, $request);
    }

    public function updateJobCategory(UpdateJobCategoryRequest $request, JobCategory $jobCategory)
    {
        $jobCategory->update($request->validated());

        return response()->json(['message' => 'Job category updated.', 'data' => $jobCategory->fresh()]);
    }

    public function storeLanguage(StoreCatalogRequest $request)
    {
        return $this->storeCatalog(Language::class, $request);
    }

    public function storeReligion(StoreCatalogRequest $request)
    {
        abort_if(strcasecmp($request->string('name')->toString(), 'christian') === 0, 422, 'This religion is not available.');

        return $this->storeCatalog(Religion::class, $request);
    }

    /**
     * @param class-string<Model> $model
     */
    private function storeCatalog(string $model, StoreCatalogRequest $request)
    {
        $item = $model::query()->updateOrCreate(
            ['name' => $request->validated('name')],
            $request->validated()
        );

        return response()->json(['message' => 'Catalog item saved.', 'data' => $item], 201);
    }

    private function ugandaLocationData(): array
    {
        static $data = null;

        if ($data !== null) {
            return $data;
        }

        $basePath = storage_path('app/public/ugandaData');
        $districts = $this->readLocationJson($basePath.'/districts.json');
        $counties = $this->readLocationJson($basePath.'/counties.json');
        $subcounties = $this->readLocationJson($basePath.'/sub_counties.json');
        $parishes = $this->readLocationJson($basePath.'/parishes.json');
        $villages = $this->readLocationJson($basePath.'/villages.json');

        if (empty($districts) || empty($counties) || empty($subcounties) || empty($parishes) || empty($villages)) {
            return $this->ugandaLocationDataFromDatabase();
        }

        return $data = [
            'districts' => $districts,
            'counties' => $counties,
            'subcounties' => $subcounties,
            'parishes' => $parishes,
            'villages' => $villages,
        ];
    }

    private function ugandaLocationDataFromDatabase(): array
    {
        $locations = UgandaLocation::query()
            ->where('is_active', true)
            ->orderBy('district')
            ->orderBy('county')
            ->orderBy('subcounty')
            ->orderBy('parish')
            ->orderBy('village')
            ->get();

        $districtNames = $locations
            ->pluck('district')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $districts = array_values(array_unique(array_merge(self::UGANDA_DISTRICTS, $districtNames)));
        $districts = array_map(fn (string $district) => ['id' => $district, 'name' => $district], $districts);

        $counties = $locations
            ->map(fn ($location) => ['id' => (string) $location->county, 'name' => $location->county, 'district' => (string) $location->district])
            ->unique(fn (array $row) => $row['district'].'|'.$row['id'])
            ->sortBy('name')
            ->values()
            ->all();

        $subcounties = $locations
            ->map(fn ($location) => ['id' => (string) $location->subcounty, 'name' => $location->subcounty, 'county' => (string) $location->county])
            ->unique(fn (array $row) => $row['county'].'|'.$row['id'])
            ->sortBy('name')
            ->values()
            ->all();

        $parishes = $locations
            ->map(fn ($location) => ['id' => (string) $location->parish, 'name' => $location->parish, 'subcounty' => (string) $location->subcounty])
            ->unique(fn (array $row) => $row['subcounty'].'|'.$row['id'])
            ->sortBy('name')
            ->values()
            ->all();

        $villages = $locations
            ->map(fn ($location) => ['id' => (string) $location->village, 'name' => $location->village, 'parish' => (string) $location->parish])
            ->unique(fn (array $row) => $row['parish'].'|'.$row['id'])
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'districts' => $districts,
            'counties' => $counties,
            'subcounties' => $subcounties,
            'parishes' => $parishes,
            'villages' => $villages,
        ];
    }

    private function readLocationJson(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true) ?: [];
    }

    private function matchingIds(array $items, ?string $selectedName): ?array
    {
        if (! $selectedName) {
            return null;
        }

        return collect($items)
            ->filter(fn (array $item) => strcasecmp($item['name'] ?? '', $selectedName) === 0)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    private function filterByParent(array $items, string $parentKey, ?array $parentIds): array
    {
        if ($parentIds === null) {
            return $items;
        }

        $ids = array_flip($parentIds);

        return array_values(array_filter(
            $items,
            fn (array $item) => isset($ids[(string) ($item[$parentKey] ?? '')])
        ));
    }

    private function names(array $items): array
    {
        return collect($items)
            ->pluck('name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
