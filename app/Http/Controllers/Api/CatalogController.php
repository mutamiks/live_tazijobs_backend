<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCatalogRequest;
use App\Http\Requests\StoreUgandaLocationRequest;
use App\Models\JobCategory;
use App\Models\Language;
use App\Models\Religion;
use App\Models\UgandaLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class CatalogController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => [
                'job_categories' => JobCategory::query()->where('is_active', true)->orderBy('name')->get(),
                'languages' => Language::query()->where('is_active', true)->orderBy('name')->get(),
                'religions' => Religion::query()->where('is_active', true)->orderBy('name')->get(),
            ],
        ]);
    }

    public function locations(Request $request)
    {
        $data = $this->ugandaLocationData();

        $districtIds = $this->matchingIds($data['districts'], $request->query('district'));
        $counties = $request->filled('district')
            ? $this->filterByParent($data['counties'], 'district', $districtIds)
            : [];

        $countyIds = $this->matchingIds($counties, $request->query('county'));
        $subcounties = $request->filled('county')
            ? $this->filterByParent($data['subcounties'], 'county', $countyIds)
            : [];

        $subcountyIds = $this->matchingIds($subcounties, $request->query('subcounty'));
        $parishes = $request->filled('subcounty')
            ? $this->filterByParent($data['parishes'], 'subcounty', $subcountyIds)
            : [];

        $parishIds = $this->matchingIds($parishes, $request->query('parish'));
        $villages = $request->filled('parish')
            ? $this->filterByParent($data['villages'], 'parish', $parishIds)
            : [];

        return response()->json([
            'data' => [
                'districts' => $this->names($data['districts']),
                'counties' => $this->names($counties),
                'subcounties' => $this->names($subcounties),
                'parishes' => $this->names($parishes),
                'villages' => $this->names($villages),
            ],
        ]);
    }

    public function adminIndex()
    {
        return response()->json([
            'data' => [
                'job_categories' => JobCategory::query()->orderBy('name')->get(),
                'languages' => Language::query()->orderBy('name')->get(),
                'religions' => Religion::query()->orderBy('name')->get(),
                'locations' => UgandaLocation::query()->orderBy('district')->orderBy('county')->orderBy('subcounty')->paginate(50),
            ],
        ]);
    }

    public function storeJobCategory(StoreCatalogRequest $request)
    {
        return $this->storeCatalog(JobCategory::class, $request);
    }

    public function storeLanguage(StoreCatalogRequest $request)
    {
        return $this->storeCatalog(Language::class, $request);
    }

    public function storeReligion(StoreCatalogRequest $request)
    {
        return $this->storeCatalog(Religion::class, $request);
    }

    public function storeLocation(StoreUgandaLocationRequest $request)
    {
        $location = UgandaLocation::query()->updateOrCreate(
            $request->safe()->only(['district', 'county', 'subcounty', 'parish', 'village']),
            $request->validated()
        );

        return response()->json(['message' => 'Location saved.', 'data' => $location], 201);
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

        return $data = [
            'districts' => $this->readLocationJson($basePath.'/districts.json'),
            'counties' => $this->readLocationJson($basePath.'/counties.json'),
            'subcounties' => $this->readLocationJson($basePath.'/sub_counties.json'),
            'parishes' => $this->readLocationJson($basePath.'/parishes.json'),
            'villages' => $this->readLocationJson($basePath.'/villages.json'),
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
