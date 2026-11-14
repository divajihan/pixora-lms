import { useCallback, useEffect, useState } from 'react';

type SnapCallbacks = {
    onSuccess?: () => void;
    onPending?: () => void;
    onError?: () => void;
    onClose?: () => void;
};

declare global {
    interface Window {
        snap?: { pay: (token: string, callbacks: SnapCallbacks) => void };
    }
}

/**
 * Loads Midtrans Snap.js once (with the public client key) and exposes pay().
 * Snap callbacks are UI hints only: the order status always comes from the
 * server (webhook / Status API), never from the browser.
 */
export function useSnap(scriptUrl: string | null, clientKey: string | null) {
    const [ready, setReady] = useState(
        () => typeof window !== 'undefined' && Boolean(window.snap),
    );
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!scriptUrl || !clientKey || window.snap) {
            return;
        }

        const existing =
            document.querySelector<HTMLScriptElement>('script[data-snap]');
        const script = existing ?? document.createElement('script');

        const onLoad = () => setReady(true);
        const onError = () => setFailed(true);
        script.addEventListener('load', onLoad);
        script.addEventListener('error', onError);

        if (!existing) {
            script.src = scriptUrl;
            script.async = true;
            script.dataset.snap = 'true';
            script.dataset.clientKey = clientKey;
            document.body.appendChild(script);
        }

        return () => {
            script.removeEventListener('load', onLoad);
            script.removeEventListener('error', onError);
        };
    }, [scriptUrl, clientKey]);

    const pay = useCallback((token: string, callbacks: SnapCallbacks) => {
        window.snap?.pay(token, callbacks);
    }, []);

    return { ready, failed, pay };
}
