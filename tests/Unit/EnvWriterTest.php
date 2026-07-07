<?php

use App\Domain\Installer\EnvWriter;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'envwriter-'.uniqid();
    mkdir($this->dir);
    $this->path = $this->dir.DIRECTORY_SEPARATOR.'.env';
});

afterEach(function () {
    foreach (['.env', '.env.example'] as $file) {
        $full = $this->dir.DIRECTORY_SEPARATOR.$file;
        if (file_exists($full)) {
            unlink($full);
        }
    }
    if (is_dir($this->dir)) {
        rmdir($this->dir);
    }
});

test('replaces existing keys and preserves everything else', function () {
    file_put_contents($this->path, implode(PHP_EOL, [
        '# Comment stays',
        'APP_NAME=Laravel',
        'APP_ENV=local',
        '',
        'DB_HOST=127.0.0.1',
    ]));

    (new EnvWriter($this->path))->write(['APP_NAME' => 'Acme', 'DB_HOST' => 'db.internal']);

    $contents = file_get_contents($this->path);
    expect($contents)->toContain('# Comment stays')
        ->and($contents)->toContain('APP_NAME=Acme')
        ->and($contents)->toContain('APP_ENV=local')
        ->and($contents)->toContain('DB_HOST=db.internal')
        ->and($contents)->not->toContain('APP_NAME=Laravel');
});

test('appends missing keys', function () {
    file_put_contents($this->path, 'APP_NAME=Acme'.PHP_EOL);

    (new EnvWriter($this->path))->write(['REVERB_APP_KEY' => 'abc123']);

    expect(file_get_contents($this->path))->toContain('REVERB_APP_KEY=abc123');
});

test('quotes values with spaces or special characters', function () {
    file_put_contents($this->path, 'APP_NAME=x'.PHP_EOL);

    (new EnvWriter($this->path))->write([
        'APP_NAME' => 'Acme Home Services',
        'DB_PASSWORD' => 'p#ss word',
        'EMPTY' => null,
    ]);

    $contents = file_get_contents($this->path);
    expect($contents)->toContain('APP_NAME="Acme Home Services"')
        ->and($contents)->toContain('DB_PASSWORD="p#ss word"')
        ->and($contents)->toContain('EMPTY=');
});

test('bootstraps from .env.example when .env is missing', function () {
    file_put_contents($this->dir.DIRECTORY_SEPARATOR.'.env.example', 'APP_NAME=Example'.PHP_EOL);

    (new EnvWriter($this->path))->write(['APP_NAME' => 'Fresh']);

    expect(file_exists($this->path))->toBeTrue()
        ->and(file_get_contents($this->path))->toContain('APP_NAME=Fresh');
});

test('does not treat prefixed keys as matches', function () {
    file_put_contents($this->path, "DB_HOST=a\nSOME_DB_HOST=b\n");

    (new EnvWriter($this->path))->write(['DB_HOST' => 'c']);

    $contents = file_get_contents($this->path);
    expect($contents)->toContain('DB_HOST=c')
        ->and($contents)->toContain('SOME_DB_HOST=b');
});
