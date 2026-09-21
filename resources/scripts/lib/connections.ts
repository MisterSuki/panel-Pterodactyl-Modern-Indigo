// eslint-disable-next-line no-control-regex
const COLOURS = /\[[0-9;]*m/g;

// "... - 3 connections." The number is only read after a dash, so "Limit: 25 connections" is not taken for a count.
const COUNT = /-\s*(\d+)\s+connections?\.?$/i;

/**
 * Some games tell how many people are connected each time someone comes or goes, for instance
 * "Incoming connection: ::ffff:86.207.48.85 - 1 connections." This reads that number from a line of the console.
 */
export const connectionCount = (line: string): number | null => {
    const text = line.replace(COLOURS, '').trim();
    if (!/connection/i.test(text)) {
        return null;
    }

    const found = COUNT.exec(text);

    return found ? parseInt(found[1], 10) : null;
};
