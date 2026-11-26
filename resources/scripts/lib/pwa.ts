import http from '@/api/http';

// The panel as an app (PWA): the service worker (notifications), the button "Install the app", and the notifications
// of the panel on this device (Web Push).

interface InstallPrompt extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

let installPrompt: InstallPrompt | null = null;
const listeners = new Set<() => void>();
const changed = () => listeners.forEach((listener) => listener());

export const onPwaChange = (listener: () => void) => {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
};

if (typeof window !== 'undefined') {
    // The browser offers to install the panel: kept for the button of the Account page.
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event as InstallPrompt;
        changed();
    });
    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        changed();
    });
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => undefined);
        });
    }
}

// Opened as an app (from the home screen or the menu of apps), not in a tab of the browser.
export const runningAsApp = (): boolean =>
    window.matchMedia('(display-mode: standalone)').matches || (navigator as any).standalone === true;

export const canInstall = (): boolean => installPrompt !== null;

export const isIos = (): boolean => /iphone|ipad|ipod/i.test(navigator.userAgent);

export const install = async (): Promise<boolean> => {
    if (!installPrompt) return false;
    const prompt = installPrompt;
    await prompt.prompt();
    const { outcome } = await prompt.userChoice;
    installPrompt = null;
    changed();

    return outcome === 'accepted';
};

export const pushSupported = (): boolean =>
    window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

const keyBytes = (base64Url: string): Uint8Array => {
    const base64 = (base64Url + '='.repeat((4 - (base64Url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);

    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
};

const registration = async (): Promise<ServiceWorkerRegistration> => {
    const existing = await navigator.serviceWorker.getRegistration('/');

    return existing || navigator.serviceWorker.register('/sw.js', { scope: '/' });
};

// The subscription of this device, if the notifications are on here.
export const currentSubscription = async (): Promise<PushSubscription | null> => {
    if (!pushSupported()) return null;
    const reg = await navigator.serviceWorker.getRegistration('/');

    return reg ? reg.pushManager.getSubscription() : null;
};

// Turns the notifications on for this device: asks the browser, then gives the panel where to send them.
export const enablePush = async (): Promise<void> => {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        throw new Error('blocked');
    }
    const { data } = await http.get('/api/client/account/push');
    const reg = await registration();
    await navigator.serviceWorker.ready;
    let subscription = await reg.pushManager.getSubscription();
    // Subscribed with another key of the panel: start again.
    const wanted = keyBytes(data.public_key);
    if (subscription && subscription.options.applicationServerKey) {
        const current = new Uint8Array(subscription.options.applicationServerKey as ArrayBuffer);
        if (current.length !== wanted.length || current.some((b, i) => b !== wanted[i])) {
            await subscription.unsubscribe();
            subscription = null;
        }
    }
    subscription =
        subscription || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: wanted }));
    await http.post('/api/client/account/push', subscription.toJSON());
};

export const disablePush = async (): Promise<void> => {
    const subscription = await currentSubscription();
    if (!subscription) return;
    await http.post('/api/client/account/push/remove', { endpoint: subscription.endpoint }).catch(() => undefined);
    await subscription.unsubscribe();
};

export const testPush = (): Promise<void> => http.post('/api/client/account/push/test').then(() => undefined);
