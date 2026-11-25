import React from 'react';

// A small and safe Markdown for the answers of the AI: code blocks, lists, titles, `code` and **bold**. Everything is
// text for React (nothing is read as HTML), so an answer cannot put anything on the page.

const inline = (text: string, key: string): React.ReactNode[] =>
    text.split(/(`[^`\n]+`|\*\*[^*\n]+\*\*)/g).map((part, index) => {
        if (part.startsWith('`') && part.endsWith('`') && part.length > 2) {
            return (
                <code
                    key={`${key}-${index}`}
                    className={'rounded bg-black/40 px-1 py-0.5 text-[0.85em] text-amber-200'}
                >
                    {part.slice(1, -1)}
                </code>
            );
        }
        if (part.startsWith('**') && part.endsWith('**') && part.length > 4) {
            return (
                <strong key={`${key}-${index}`} className={'font-semibold text-neutral-50'}>
                    {part.slice(2, -2)}
                </strong>
            );
        }

        return part;
    });

const LIST = /^\s*([-*•]|\d+[.)])\s+/;

const blocks = (text: string, key: string): React.ReactNode[] => {
    const out: React.ReactNode[] = [];
    const lines = text.split('\n');
    let paragraph: string[] = [];
    let list: { ordered: boolean; items: string[] } | null = null;

    const flushParagraph = () => {
        if (paragraph.length) {
            const n = out.length;
            out.push(
                <p key={`${key}-p${n}`} className={'my-1.5'}>
                    {paragraph.map((line, i) => (
                        <React.Fragment key={i}>
                            {i > 0 && <br />}
                            {inline(line, `${key}-p${n}-${i}`)}
                        </React.Fragment>
                    ))}
                </p>
            );
            paragraph = [];
        }
    };
    const flushList = () => {
        if (list) {
            const n = out.length;
            const items = list.items.map((item, i) => (
                <li key={i} className={'my-0.5'}>
                    {inline(item, `${key}-l${n}-${i}`)}
                </li>
            ));
            out.push(
                list.ordered ? (
                    <ol key={`${key}-l${n}`} className={'my-1.5 pl-5 list-decimal space-y-0.5'}>
                        {items}
                    </ol>
                ) : (
                    <ul key={`${key}-l${n}`} className={'my-1.5 pl-5 list-disc space-y-0.5'}>
                        {items}
                    </ul>
                )
            );
            list = null;
        }
    };

    lines.forEach((line) => {
        const trimmed = line.trim();
        if (trimmed === '') {
            flushParagraph();
            flushList();

            return;
        }
        const title = /^#{1,6}\s+(.*)$/.exec(trimmed);
        if (title) {
            flushParagraph();
            flushList();
            out.push(
                <p key={`${key}-h${out.length}`} className={'mt-3 mb-1 font-semibold text-neutral-50'}>
                    {inline(title[1], `${key}-h${out.length}`)}
                </p>
            );

            return;
        }
        const item = LIST.exec(line);
        if (item) {
            flushParagraph();
            const ordered = /\d/.test(item[1]);
            if (!list || list.ordered !== ordered) {
                flushList();
                list = { ordered, items: [] };
            }
            list.items.push(line.slice(item[0].length));

            return;
        }
        if (list && /^\s{2,}/.test(line)) {
            // The rest of a list item, on the next line.
            list.items[list.items.length - 1] += ' ' + trimmed;

            return;
        }
        flushList();
        paragraph.push(line);
    });
    flushParagraph();
    flushList();

    return out;
};

export default ({ text }: { text: string }) => {
    const parts = text.split('```');

    return (
        <div className={'text-sm leading-relaxed text-neutral-200 break-words'}>
            {parts.map((part, index) => {
                if (index % 2 === 1) {
                    // A code block: the first line may be its language.
                    const code = part.replace(/^[A-Za-z0-9_+-]*\n/, '').replace(/\n$/, '');

                    return (
                        <pre
                            key={index}
                            className={
                                'my-2 overflow-x-auto rounded-lg border border-white/10 bg-black/50 p-3 text-xs leading-5 text-neutral-100'
                            }
                        >
                            <code>{code}</code>
                        </pre>
                    );
                }

                return <React.Fragment key={index}>{blocks(part, String(index))}</React.Fragment>;
            })}
        </div>
    );
};
