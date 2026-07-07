<?php

use App\Domain\Zones\PointInPolygon;

function squarePolygon(): array
{
    return [
        'type' => 'Polygon',
        'coordinates' => [[
            [77.50, 12.90],
            [77.70, 12.90],
            [77.70, 13.10],
            [77.50, 13.10],
            [77.50, 12.90],
        ]],
    ];
}

test('point inside the polygon is detected', function () {
    expect(PointInPolygon::contains(squarePolygon(), 13.00, 77.60))->toBeTrue();
});

test('point outside the polygon is rejected', function () {
    expect(PointInPolygon::contains(squarePolygon(), 12.50, 77.60))->toBeFalse()
        ->and(PointInPolygon::contains(squarePolygon(), 13.00, 77.80))->toBeFalse();
});

test('point inside a hole is rejected', function () {
    $withHole = squarePolygon();
    $withHole['coordinates'][] = [
        [77.58, 12.98],
        [77.62, 12.98],
        [77.62, 13.02],
        [77.58, 13.02],
        [77.58, 12.98],
    ];

    expect(PointInPolygon::contains($withHole, 13.00, 77.60))->toBeFalse()
        ->and(PointInPolygon::contains($withHole, 12.95, 77.60))->toBeTrue();
});

test('malformed geometry is rejected instead of throwing', function () {
    expect(PointInPolygon::contains([], 13.00, 77.60))->toBeFalse()
        ->and(PointInPolygon::contains(['type' => 'Point'], 13.00, 77.60))->toBeFalse()
        ->and(PointInPolygon::contains(['type' => 'Polygon'], 13.00, 77.60))->toBeFalse()
        ->and(PointInPolygon::contains(['type' => 'Polygon', 'coordinates' => []], 13.00, 77.60))->toBeFalse()
        ->and(PointInPolygon::contains(['type' => 'Polygon', 'coordinates' => ['bad']], 13.00, 77.60))->toBeFalse();
});
