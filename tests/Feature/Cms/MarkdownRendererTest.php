<?php

use App\Domain\Cms\MarkdownRenderer;

it('renders markdown structure to html', function () {
    $html = app(MarkdownRenderer::class)->render("## Heading\n\n- one\n- two\n\n[Link](https://example.com)");

    expect($html)
        ->toContain('<h2>Heading</h2>')
        ->toContain('<li>one</li>')
        ->toContain('<a href="https://example.com">Link</a>');
});

it('strips raw html from the source', function () {
    $html = app(MarkdownRenderer::class)->render("Hello <script>alert(1)</script> world\n\n<img src=x onerror=alert(1)>");

    expect($html)
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->toContain('Hello');
});

it('drops unsafe link schemes', function () {
    $html = app(MarkdownRenderer::class)->render('[click](javascript:alert(1)) and [data](data:text/html;base64,x)');

    expect($html)
        ->not->toContain('javascript:')
        ->not->toContain('data:text/html');
});
