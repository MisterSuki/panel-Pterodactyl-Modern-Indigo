// The text of the console of the server being looked at (everything above in it too), for the AI assistant: the
// console registers how to read it while it is on the page.
let reader: (() => string) | null = null;

export const setConsoleReader = (read: (() => string) | null) => {
    reader = read;
};

export const readConsole = (): string => {
    try {
        return reader ? reader() : '';
    } catch {
        return '';
    }
};
