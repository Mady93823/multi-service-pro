<?php

use App\Domain\Installer\RequirementsChecker;

test('runs a structured check list', function () {
    $result = (new RequirementsChecker)->run();

    expect($result)->toHaveKeys(['passed', 'checks'])
        ->and($result['checks'])->not->toBeEmpty();

    foreach ($result['checks'] as $check) {
        expect($check)->toHaveKeys(['label', 'passed', 'detail']);
    }
});

test('php version check passes on the running interpreter', function () {
    $result = (new RequirementsChecker)->run();

    $php = collect($result['checks'])->firstWhere(fn (array $check) => str_starts_with($check['label'], 'PHP >='));

    expect($php)->not->toBeNull()
        ->and($php['passed'])->toBeTrue();
});
