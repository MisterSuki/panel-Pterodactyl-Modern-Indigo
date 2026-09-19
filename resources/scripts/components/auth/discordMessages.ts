import { FlashMessageType } from '@/components/MessageBox';

export interface DiscordMessage {
    type: FlashMessageType;
    message: string;
}

// The server reports the outcome of a Discord attempt with one of these codes in the
// `discord` query parameter. Only known codes are ever displayed.
const messages: Record<string, DiscordMessage> = {
    linked: { type: 'success', message: 'Your Discord account is now linked.' },
    disabled: { type: 'error', message: 'Discord login is not available on this Panel.' },
    cancelled: { type: 'error', message: 'Discord login was cancelled.' },
    failed: { type: 'error', message: 'Unable to sign in with Discord. Please try again.' },
    taken: { type: 'error', message: 'That Discord account is already linked to another account.' },
    other_discord: {
        type: 'error',
        message: 'The account using this email address is already linked to a different Discord account.',
    },
    admin_link: {
        type: 'error',
        message:
            'Administrator and staff accounts cannot be linked automatically. Sign in with your password, then link Discord from your account page.',
    },
    no_account: {
        type: 'error',
        message: 'No account matches this Discord account, and registration is closed.',
    },
    no_email: {
        type: 'error',
        message: 'Your Discord account needs a verified email address to create an account here.',
    },
};

export default (code: string | null): DiscordMessage | null =>
    code && Object.prototype.hasOwnProperty.call(messages, code) ? messages[code] : null;
