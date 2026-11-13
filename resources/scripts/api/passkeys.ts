import http from '@/api/http';
import { LoginResponse } from '@/api/auth/login';

// Passkeys (WebAuthn): signing in with Face ID, Touch ID, Windows Hello, a phone or a security key. The panel and the
// browser exchange binary data as base64url text.

const toBuffer = (text: string): ArrayBuffer => {
    const b64 = text.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(b64 + '='.repeat((4 - (b64.length % 4)) % 4));
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

    return bytes.buffer;
};

const toText = (buffer: ArrayBuffer | null | undefined): string => {
    if (!buffer) return '';
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};

// Whether this browser can use passkeys at all (it also needs HTTPS).
export const passkeysSupported = (): boolean =>
    typeof window !== 'undefined' && !!window.PublicKeyCredential && !!navigator.credentials && window.isSecureContext;

// A readable message for what the browser says when the passkey window is closed or refused; null for other errors
// (those come from the panel and are shown as usual).
export const passkeyError = (error: any): string | null => {
    if (!error || typeof error.name !== 'string' || error.response) return null;
    switch (error.name) {
        case 'NotAllowedError':
        case 'AbortError':
            return 'The passkey request was cancelled or took too long.';
        case 'InvalidStateError':
            return 'This device already holds a passkey for your account.';
        case 'SecurityError':
            return 'Passkeys only work on the address of the panel, in HTTPS.';
        case 'NotSupportedError':
            return 'This device cannot make a passkey.';
        default:
            return error.message || null;
    }
};

// A name for a new passkey, from the device it is made on.
export const deviceName = (): string => {
    const ua = navigator.userAgent;
    if (/iPhone/.test(ua)) return 'iPhone';
    if (/iPad/.test(ua)) return 'iPad';
    if (/Macintosh/.test(ua)) return navigator.maxTouchPoints > 1 ? 'iPad' : 'Mac';
    if (/Android/.test(ua)) return 'Android';
    if (/Windows/.test(ua)) return 'Windows Hello';
    if (/CrOS/.test(ua)) return 'Chromebook';
    if (/Linux/.test(ua)) return 'Linux';

    return 'Passkey';
};

export const loginWithPasskey = async (): Promise<LoginResponse> => {
    await http.get('/sanctum/csrf-cookie');
    const { data: options } = await http.post('/auth/passkey/options');
    const credential = (await navigator.credentials.get({
        publicKey: {
            challenge: toBuffer(options.challenge),
            rpId: options.rpId,
            timeout: options.timeout,
            userVerification: options.userVerification,
            allowCredentials: [],
        },
    })) as PublicKeyCredential | null;
    if (!credential) throw Object.assign(new Error('cancelled'), { name: 'NotAllowedError' });

    const response = credential.response as AuthenticatorAssertionResponse;
    const { data } = await http.post('/auth/passkey/login', {
        id: toText(credential.rawId),
        client_data: toText(response.clientDataJSON),
        authenticator_data: toText(response.authenticatorData),
        signature: toText(response.signature),
        user_handle: response.userHandle ? toText(response.userHandle) : null,
    });

    return { complete: data.data.complete, intended: data.data.intended || undefined };
};

export interface Passkey {
    id: number;
    name: string;
    site: string;
    createdAt: Date | null;
    lastUsedAt: Date | null;
}

/* eslint-disable camelcase */
const map = (list: any[]): Passkey[] =>
    (list || []).map((p) => ({
        id: p.id,
        name: p.name,
        site: p.site,
        createdAt: p.created_at ? new Date(p.created_at) : null,
        lastUsedAt: p.last_used_at ? new Date(p.last_used_at) : null,
    }));
/* eslint-enable camelcase */

export const getPasskeys = (): Promise<Passkey[]> =>
    http.get('/api/client/account/passkeys').then(({ data }) => map(data.data));

export const addPasskey = async (name: string): Promise<Passkey[]> => {
    const { data: options } = await http.post('/api/client/account/passkeys/options');
    const credential = (await navigator.credentials.create({
        publicKey: {
            ...options,
            challenge: toBuffer(options.challenge),
            user: { ...options.user, id: toBuffer(options.user.id) },
            excludeCredentials: (options.excludeCredentials || []).map((c: { id: string }) => ({
                type: 'public-key',
                id: toBuffer(c.id),
            })),
        },
    })) as PublicKeyCredential | null;
    if (!credential) throw Object.assign(new Error('cancelled'), { name: 'NotAllowedError' });

    // getPublicKey() gives the key in a standard form, so the panel has nothing to decode. Every recent browser has it.
    const response = credential.response as any;
    const publicKey = typeof response.getPublicKey === 'function' ? response.getPublicKey() : null;
    if (!publicKey) {
        throw Object.assign(new Error('This browser is too old to add a passkey. Update it, or use another one.'), {
            name: 'Outdated',
        });
    }

    const { data } = await http.post('/api/client/account/passkeys', {
        name,
        id: toText(credential.rawId),
        client_data: toText(response.clientDataJSON),
        authenticator_data: toText(response.getAuthenticatorData()),
        public_key: toText(publicKey),
        algorithm: response.getPublicKeyAlgorithm(),
        transports: typeof response.getTransports === 'function' ? response.getTransports() : [],
    });

    return map(data.data);
};

export const deletePasskey = (id: number): Promise<Passkey[]> =>
    http.delete(`/api/client/account/passkeys/${id}`).then(({ data }) => map(data.data));
