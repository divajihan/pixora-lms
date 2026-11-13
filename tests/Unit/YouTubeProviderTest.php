<?php

use App\Rules\VideoUrl;
use App\Services\Video\VideoProvider;
use App\Services\Video\YouTubeProvider;
use Illuminate\Support\Facades\Validator;

const VIDEO_ID = 'M7lc1UVf-VE';

test('it extracts the id from every supported YouTube format', function (string $input) {
    expect((new YouTubeProvider)->parseReference($input))->toBe(VIDEO_ID);
})->with([
    'bare id' => VIDEO_ID,
    'bare id with spaces' => '  '.VIDEO_ID.'  ',
    'watch' => 'https://www.youtube.com/watch?v='.VIDEO_ID,
    'watch with extra params' => 'https://www.youtube.com/watch?list=PL123&v='.VIDEO_ID.'&t=42s',
    'watch without www' => 'https://youtube.com/watch?v='.VIDEO_ID,
    'watch without scheme' => 'www.youtube.com/watch?v='.VIDEO_ID,
    'watch http' => 'http://www.youtube.com/watch?v='.VIDEO_ID,
    'mobile' => 'https://m.youtube.com/watch?v='.VIDEO_ID,
    'music' => 'https://music.youtube.com/watch?v='.VIDEO_ID,
    'short link' => 'https://youtu.be/'.VIDEO_ID,
    'short link with timestamp' => 'https://youtu.be/'.VIDEO_ID.'?t=10',
    'embed' => 'https://www.youtube.com/embed/'.VIDEO_ID,
    'embed with params' => 'https://www.youtube.com/embed/'.VIDEO_ID.'?start=5',
    'nocookie embed' => 'https://www.youtube-nocookie.com/embed/'.VIDEO_ID,
    'shorts' => 'https://www.youtube.com/shorts/'.VIDEO_ID,
    'shorts with query' => 'https://youtube.com/shorts/'.VIDEO_ID.'?feature=share',
    'live' => 'https://www.youtube.com/live/'.VIDEO_ID,
    'legacy /v/' => 'https://www.youtube.com/v/'.VIDEO_ID,
    'uppercase host' => 'https://WWW.YOUTUBE.COM/watch?v='.VIDEO_ID,
]);

test('it rejects non-YouTube and malformed input', function (string $input) {
    expect((new YouTubeProvider)->parseReference($input))->toBeNull();
})->with([
    'empty' => '',
    'vimeo' => 'https://vimeo.com/123456789',
    'lookalike host' => 'https://youtube.com.evil.test/watch?v='.VIDEO_ID,
    'lookalike subdomain' => 'https://notyoutube.com/watch?v='.VIDEO_ID,
    'id too short' => 'abc123',
    'id too long' => VIDEO_ID.'X',
    'id with invalid chars' => 'M7lc1UVf!VE',
    'watch without v' => 'https://www.youtube.com/watch?list=PL123',
    'channel page' => 'https://www.youtube.com/@pixora',
    'playlist' => 'https://www.youtube.com/playlist?list=PL1234567890',
    'javascript uri' => 'javascript:alert(1)',
    'youtu.be without id' => 'https://youtu.be/',
    'v array injection' => 'https://www.youtube.com/watch?v[]='.VIDEO_ID,
]);

test('it builds privacy-friendly embed, thumbnail and watch URLs', function () {
    $provider = new YouTubeProvider;

    expect($provider->key())->toBe('youtube')
        ->and($provider->embedUrl(VIDEO_ID))->toStartWith('https://www.youtube-nocookie.com/embed/'.VIDEO_ID)
        ->and($provider->thumbnailUrl(VIDEO_ID))->toBe('https://i.ytimg.com/vi/'.VIDEO_ID.'/hqdefault.jpg')
        ->and($provider->watchUrl(VIDEO_ID))->toBe('https://www.youtube.com/watch?v='.VIDEO_ID);
});

test('the container resolves YouTube as the video provider', function () {
    expect(app(VideoProvider::class))->toBeInstanceOf(YouTubeProvider::class);
});

test('the VideoUrl rule rejects non-YouTube URLs with an Indonesian message', function () {
    $validator = Validator::make(
        ['video_url' => 'https://vimeo.com/1'],
        ['video_url' => [new VideoUrl(new YouTubeProvider)]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('video_url'))->toContain('tautan YouTube yang valid');

    expect(Validator::make(
        ['video_url' => 'https://youtu.be/'.VIDEO_ID],
        ['video_url' => [new VideoUrl(new YouTubeProvider)]],
    )->passes())->toBeTrue();
});
