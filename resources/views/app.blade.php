<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        {{--
            Server-rendered title/description from the page's `meta` prop so crawlers see them
            without JS. On the client, <SeoHead> (Inertia <Head>) takes over: the description
            tag shares the `data-inertia="description"` key so it is replaced, not duplicated.
        --}}
        @php
            $metaTitle = $page['props']['meta']['title'] ?? null;
            $metaDescription = $page['props']['meta']['description']
                ?? 'Belajar online di '.config('app.name').': kursus video, rangkuman materi, tugas, dan sertifikat.';
        @endphp
        <x-inertia::head>
            <title>{{ $metaTitle ? $metaTitle.' - '.config('app.name') : config('app.name') }}</title>
            <meta name="description" content="{{ $metaDescription }}" data-inertia="description">
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
