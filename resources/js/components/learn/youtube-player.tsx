import { useEffect, useRef } from 'react';

type YTPlayer = { destroy: () => void };
type YTNamespace = {
    Player: new (
        element: HTMLIFrameElement,
        options: {
            events: { onStateChange: (event: { data: number }) => void };
        },
    ) => YTPlayer;
    PlayerState: { ENDED: number };
};

declare global {
    interface Window {
        YT?: YTNamespace;
        onYouTubeIframeAPIReady?: () => void;
    }
}

let apiPromise: Promise<YTNamespace> | null = null;

function loadYouTubeApi(): Promise<YTNamespace> {
    if (window.YT?.Player) {
        return Promise.resolve(window.YT);
    }

    apiPromise ??= new Promise((resolve) => {
        const previous = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            previous?.();
            resolve(window.YT as YTNamespace);
        };
        const script = document.createElement('script');
        script.src = 'https://www.youtube.com/iframe_api';
        script.async = true;
        document.body.appendChild(script);
    });

    return apiPromise;
}

/**
 * Privacy-friendly YouTube embed (youtube-nocookie.com via VideoProvider).
 * When the video ends, onEnded fires (used to mark the lesson completed via
 * the same endpoint as the button). If the IFrame API fails to load, the
 * video still plays; only auto-complete is lost.
 */
export function YouTubePlayer({
    embedUrl,
    title,
    onEnded,
}: {
    embedUrl: string;
    title: string;
    onEnded?: () => void;
}) {
    const iframe = useRef<HTMLIFrameElement>(null);
    const ended = useRef(onEnded);
    ended.current = onEnded;

    // No `origin` parameter: it would differ between SSR and the browser (hydration mismatch).
    const src = `${embedUrl}${embedUrl.includes('?') ? '&' : '?'}enablejsapi=1`;

    useEffect(() => {
        let player: YTPlayer | null = null;
        let cancelled = false;

        loadYouTubeApi()
            .then((YT) => {
                if (!cancelled && iframe.current) {
                    player = new YT.Player(iframe.current, {
                        events: {
                            onStateChange: (event) => {
                                if (event.data === YT.PlayerState.ENDED) {
                                    ended.current?.();
                                }
                            },
                        },
                    });
                }
            })
            .catch(() => undefined);

        return () => {
            cancelled = true;
            player?.destroy?.();
        };
    }, [src]);

    return (
        <div className="aspect-video w-full overflow-hidden rounded-lg bg-black">
            <iframe
                ref={iframe}
                src={src}
                title={title}
                className="size-full"
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                referrerPolicy="strict-origin-when-cross-origin"
                allowFullScreen
            />
        </div>
    );
}
