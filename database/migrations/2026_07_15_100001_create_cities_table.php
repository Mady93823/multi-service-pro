<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * M25. Zones carried a free-text `city` string since M03, so two zones in
     * the same town could disagree on its spelling and nothing could hang off
     * it. A city becomes a row: zones belong to it, the storefront switcher
     * lists it, and its timezone decides what "9:00 AM" means locally.
     *
     * The string column is backfilled into rows (one per distinct spelling,
     * centred on the mean of its zones' polygon points — geo math in PHP, D12)
     * and then dropped: there is one name for a city, not two.
     */
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('state', 120)->nullable();
            $table->string('timezone', 64);
            $table->decimal('center_lat', 10, 7);
            $table->decimal('center_lng', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $this->backfill();

        Schema::table('zones', function (Blueprint $table) {
            $table->dropIndex('zones_city_is_active_index');
            $table->dropColumn('city');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable(false)->change();
            $table->index(['city_id', 'is_active']);
        });
    }

    /**
     * Every existing zone already names its city; turn each distinct name into
     * a row and point its zones at it.
     */
    private function backfill(): void
    {
        $timezone = DB::table('settings')->where('key', 'localization.timezone')->value('value');
        $timezone = is_string($timezone) && $timezone !== '' ? $timezone : 'Asia/Kolkata';

        $grouped = DB::table('zones')->select('id', 'city', 'geojson')->get()->groupBy('city');
        $sort = 0;

        foreach ($grouped as $name => $zones) {
            [$lat, $lng] = $this->center($zones->pluck('geojson')->all());

            $cityId = DB::table('cities')->insertGetId([
                'name' => (string) $name,
                'slug' => $this->slug((string) $name),
                'timezone' => $timezone,
                'center_lat' => $lat,
                'center_lng' => $lng,
                'is_active' => true,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('zones')->whereIn('id', $zones->pluck('id')->all())->update(['city_id' => $cityId]);
        }
    }

    /**
     * Mean of every polygon vertex the city's zones own — close enough to drop
     * a map pin on, and it needs no spatial extension to compute.
     *
     * @param  list<mixed>  $geojson
     * @return array{float, float}
     */
    private function center(array $geojson): array
    {
        $lats = [];
        $lngs = [];

        foreach ($geojson as $raw) {
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

            if (! is_array($decoded) || ! isset($decoded['coordinates'][0]) || ! is_array($decoded['coordinates'][0])) {
                continue;
            }

            foreach ($decoded['coordinates'][0] as $point) {
                if (is_array($point) && count($point) >= 2) {
                    $lngs[] = (float) $point[0];
                    $lats[] = (float) $point[1];
                }
            }
        }

        if ($lats === []) {
            return [0.0, 0.0];
        }

        return [
            round(array_sum($lats) / count($lats), 7),
            round(array_sum($lngs) / count($lngs), 7),
        ];
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'city';
        $slug = $base;
        $suffix = 2;

        while (DB::table('cities')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->string('city', 120)->default('')->after('id');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropIndex('zones_city_id_is_active_index');
            $table->dropColumn('city_id');
            $table->index(['city', 'is_active']);
        });

        Schema::dropIfExists('cities');
    }
};
