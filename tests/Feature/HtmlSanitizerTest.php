<?php

use App\Support\HtmlSanitizer;

function clean(?string $html): ?string
{
    return app(HtmlSanitizer::class)->clean($html);
}

test('script tags and their content are removed', function () {
    expect(clean('<p>Halo</p><script>alert("xss")</script>'))
        ->toBe('<p>Halo</p>')
        ->not->toContain('script')
        ->not->toContain('alert');
});

test('event handler attributes are removed', function (string $html) {
    $result = (string) clean($html);

    expect($result)->not->toMatch('/\bon[a-z]+\s*=/i')->not->toContain('alert');
})->with([
    'img onerror' => '<p>Teks<img src="x" onerror="alert(1)"></p>',
    'p onclick' => '<p onclick="alert(1)">Teks</p>',
    'a onmouseover' => '<a href="https://ok.test" onmouseover="alert(1)">tautan</a>',
    'svg onload' => '<svg onload="alert(1)"><p>Teks</p></svg>',
]);

test('dangerous elements and URLs are stripped', function (string $html, string $forbidden) {
    expect((string) clean($html))->not->toContain($forbidden);
})->with([
    'iframe' => ['<p>a</p><iframe src="https://evil.test"></iframe>', 'iframe'],
    'object' => ['<object data="x.swf"></object><p>a</p>', 'object'],
    'style tag' => ['<style>body{display:none}</style><p>a</p>', 'style'],
    'inline style' => ['<p style="position:fixed;top:0">a</p>', 'style'],
    'javascript href' => ['<a href="javascript:alert(1)">x</a>', 'javascript'],
    'data href' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:'],
    'form' => ['<form action="https://evil.test"><input name="x"></form><p>a</p>', 'form'],
    'class attribute' => ['<p class="fixed inset-0">a</p>', 'class'],
]);

test('allowed formatting from the editor is kept', function () {
    $html = '<h2>Judul</h2><p><strong>Tebal</strong> <em>miring</em> <u>garis</u> <s>coret</s> <code>kode</code></p>'
        .'<ul><li>satu</li></ul><ol><li>dua</li></ol><blockquote><p>kutipan</p></blockquote><pre><code>x = 1</code></pre><hr>';

    $result = (string) clean($html);

    foreach (['<h2>', '<strong>', '<em>', '<u>', '<s>', '<code>', '<ul>', '<ol>', '<li>', '<blockquote>', '<pre>', '<hr'] as $tag) {
        expect($result)->toContain($tag);
    }
});

test('links are kept but forced to open safely', function () {
    expect((string) clean('<p><a href="https://contoh.test/materi">Materi</a></p>'))
        ->toContain('href="https://contoh.test/materi"')
        ->toContain('target="_blank"')
        ->toContain('noopener');
});

test('empty editor output becomes null', function (?string $html) {
    expect(clean($html))->toBeNull();
})->with([null, '', '<p></p>', '<p> </p>', '<script>x</script>']);
