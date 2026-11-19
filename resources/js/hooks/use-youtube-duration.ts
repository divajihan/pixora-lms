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
          status: 'idle' | 'loading' | 'error';
          videoId: string | null;
          seconds: null;
      }
    | { status: 'done'; videoId: string; seconds: number };

/**
 * Reads a YouTube video's length in the browser with a hidden IFrame API
 * player (no API key). Only a suggestion for the duration field; the admin
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
        const host = document.createElement('div');
        host.setAttribute('aria-hidden', 'true');
        host.style.cssText =
            'position:fixed;left:-10000px;top:0;width:1px;height:1px;overflow:hidden;';
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
                        width: 1,
                        height: 1,
                        playerVars: { autoplay: 0, controls: 0 },
                        events: {
                            onReady: (event) => {
                                // getDuration() can be 0 until metadata loads.
                                const read = () => {
                                    const seconds = Math.round(
                                        event.target.getDuration?.() ?? 0,
                                    );

                                    if (cancelled) {
                                        return;
                                    }

                                    if (seconds > 0) {
                                        setResult({
                                            status: 'done',
                                            videoId,
                                            seconds,
                                        });
                                    } else if (attempts++ < 10) {
                                        timer = window.setTimeout(read, 300);
                                    } else {
                                        setResult({
                                            status: 'error',
                                            videoId,
                                            seconds: null,
                                        });
                                    }
                                };
                                read();
                            },
                            onError: () => {
                                if (!cancelled) {
                                    setResult({
                                        status: 'error',
                                        videoId,
                                        seconds: null,
                                    });
                                }
                            },
                        },
                    });
                })
                .catch(() => {
                    if (!cancelled) {
                        setResult({ status: 'error', videoId, seconds: null });
                    }
                });
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
