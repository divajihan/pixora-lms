import { useEffect, useState } from 'react';
import { loadYouTubeApi } from '@/components/learn/youtube-player';

/** YouTube video ID from the URL formats the server accepts, or null. */
export function youtubeId(url: string): string | null {
    const match = url
        .trim()
        .match(
            /(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/,
        );

    return match ? match[1] : null;
}

export type VideoDuration =
    | {
          status: 'idle' | 'loading';
          videoId: string | null;
          seconds: null;
      }
    | {
          status: 'error';
          videoId: string;
          seconds: null;
          /** not_found: private/deleted (YouTube 100); no_embed: owner blocks embedding (101/150). */
          reason: 'not_found' | 'no_embed' | 'unknown';
      }
    | { status: 'done'; videoId: string; seconds: number };

/**
 * Reads a YouTube video's length in the browser with an off-screen IFrame API
 * player (no API key). YouTube often reports 0 until playback starts, so the
 * player is started muted (allowed without a user gesture) and paused as soon
 * as the length is known. Only a suggestion for the duration field: the admin
 * can still change it, and the server validates the number as usual.
 */
export function useYouTubeDuration(url: string): VideoDuration {
    const videoId = youtubeId(url);
    const [result, setResult] = useState<VideoDuration>({
        status: 'idle',
        videoId: null,
        seconds: null,
    });

    useEffect(() => {
        if (!videoId) {
            setResult({ status: 'idle', videoId: null, seconds: null });

            return;
        }

        let cancelled = false;
        let attempts = 0;
        let timer: number | undefined;
        let player: { destroy: () => void } | null = null;
        const fail = (event?: { data: number }) => {
            if (!cancelled) {
                const code = event?.data;
                setResult({
                    status: 'error',
                    videoId,
                    seconds: null,
                    reason:
                        code === 100
                            ? 'not_found'
                            : code === 101 || code === 150
                              ? 'no_embed'
                              : 'unknown',
                });
            }
        };

        // Off-screen but full size: YouTube refuses very small players.
        const host = document.createElement('div');
        host.setAttribute('aria-hidden', 'true');
        host.style.cssText =
            'position:fixed;left:-10000px;top:0;width:320px;height:180px;opacity:0;pointer-events:none;';
        const target = document.createElement('div');
        host.appendChild(target);
        document.body.appendChild(host);

        setResult({ status: 'loading', videoId, seconds: null });

        // Debounce typing in the URL field.
        const start = window.setTimeout(() => {
            loadYouTubeApi()
                .then((YT) => {
                    if (cancelled) {
                        return;
                    }

                    player = new YT.Player(target, {
                        videoId,
                        width: 320,
                        height: 180,
                        playerVars: {
                            autoplay: 0,
                            controls: 0,
                            playsinline: 1,
                        },
                        events: {
                            onReady: (event) => {
                                const video = event.target;
                                const read = () => {
                                    if (cancelled) {
                                        return;
                                    }

                                    const seconds = Math.round(
                                        video.getDuration?.() ?? 0,
                                    );

                                    if (seconds > 0) {
                                        video.pauseVideo?.();
                                        setResult({
                                            status: 'done',
                                            videoId,
                                            seconds,
                                        });
                                    } else if (attempts++ < 25) {
                                        timer = window.setTimeout(read, 400);
                                    } else {
                                        fail();
                                    }
                                };

                                if ((video.getDuration?.() ?? 0) <= 0) {
                                    video.mute?.();
                                    video.playVideo?.();
                                }

                                read();
                            },
                            onError: fail,
                        },
                    });
                })
                .catch(() => fail());
        }, 400);

        return () => {
            cancelled = true;
            window.clearTimeout(start);
            window.clearTimeout(timer);
            player?.destroy();
            host.remove();
        };
    }, [videoId]);

    return result;
}
