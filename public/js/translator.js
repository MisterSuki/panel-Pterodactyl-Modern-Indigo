/*
 * Shows the panel in the language of the person using it.
 *
 * The pages are written in English. For another language, this script loads a dictionary that maps each English
 * text to its translation (resources/lang/<code>.json, served at /locales/ui.json) and swaps the texts of the
 * page, and of the pages that appear later (the dashboard draws its screens by itself), in place. Only whole texts
 * that are in the dictionary are changed, so names and values typed by users are left alone, as are the console,
 * the code editor and form fields.
 *
 * The language is, in this order: the one of the signed-in account, the one a visitor picked, the panel's default.
 */
(function () {
    'use strict';

    var STORAGE_LANGUAGE = 'pd_lang';
    var STORAGE_DICTIONARY = 'pd_dict:';
    var SKIPPED_TAGS = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, INPUT: 1, CODE: 1, PRE: 1, NOSCRIPT: 1, IFRAME: 1, SVG: 1 };
    var SKIPPED_ANCESTORS = '.xterm, .cm-editor, .CodeMirror, .ace_editor, [contenteditable="true"], [data-no-translate]';
    var ATTRIBUTES = ['placeholder', 'title', 'aria-label', 'alt'];

    var dictionary = null;
    var pending = [];
    var scheduled = false;

    function read(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function write(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (e) {
            /* private mode or full storage: the language is simply not remembered */
        }
    }

    function currentLanguage() {
        var account = window.PterodactylUser && window.PterodactylUser.language;
        var site = (window.SiteConfiguration && window.SiteConfiguration.locale) || window.PterodactylSiteLocale || 'en';

        return window.PterodactylUiLocale || account || read(STORAGE_LANGUAGE) || site;
    }

    // One space between words and no spaces around, whatever the markup did (line breaks, non-breaking spaces).
    function normalize(text) {
        return text.replace(/\s+/g, ' ').trim();
    }

    function lookup(text) {
        if (!dictionary) {
            return null;
        }
        var match = /^(\s*)([\s\S]*?)(\s*)$/.exec(text);
        if (!match || match[2] === '') {
            return null;
        }
        var translated = dictionary[normalize(match[2])];

        return typeof translated === 'string' && translated !== '' ? match[1] + translated + match[3] : null;
    }

    // Inside the console, the code editor and marked areas nothing is touched.
    function insideSkippedArea(element) {
        return !!element && typeof element.closest === 'function' && element.closest(SKIPPED_ANCESTORS) !== null;
    }

    // Text is not looked for inside scripts, form fields, code and the like.
    function skipped(element) {
        return !element || !!SKIPPED_TAGS[String(element.tagName).toUpperCase()] || insideSkippedArea(element);
    }

    function translateText(node) {
        if (skipped(node.parentElement)) {
            return;
        }
        var translated = lookup(node.nodeValue);
        if (translated !== null && translated !== node.nodeValue) {
            node.nodeValue = translated;
        }
    }

    function translateAttributes(element) {
        // The placeholder of a field is translated even though the field itself is not read.
        if (insideSkippedArea(element)) {
            return;
        }
        for (var i = 0; i < ATTRIBUTES.length; i++) {
            var value = element.getAttribute(ATTRIBUTES[i]);
            if (value) {
                var translated = lookup(value);
                if (translated !== null && translated !== value) {
                    element.setAttribute(ATTRIBUTES[i], translated);
                }
            }
        }
        // Buttons made with <input> show their value.
        if (element.tagName === 'INPUT' && /^(submit|button|reset)$/i.test(element.type)) {
            var label = lookup(element.value);
            if (label !== null) {
                element.value = label;
            }
        }
    }

    function translateTree(root) {
        if (!root) {
            return;
        }
        if (root.nodeType === 3) {
            translateText(root);

            return;
        }
        if (root.nodeType !== 1 || insideSkippedArea(root)) {
            return;
        }
        if (skipped(root)) {
            translateAttributes(root);

            return;
        }

        translateAttributes(root);
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (node.nodeType === 1 && skipped(node)) {
                    // Nothing inside is read, but a field can still have a placeholder to translate.
                    translateAttributes(node);

                    return NodeFilter.FILTER_REJECT;
                }

                return NodeFilter.FILTER_ACCEPT;
            },
        });
        var node = walker.nextNode();
        while (node) {
            if (node.nodeType === 3) {
                translateText(node);
            } else {
                translateAttributes(node);
            }
            node = walker.nextNode();
        }
    }

    function flush() {
        scheduled = false;
        var work = pending;
        pending = [];
        for (var i = 0; i < work.length; i++) {
            var item = work[i];
            if (item.type === 'attributes') {
                translateAttributes(item.target);
            } else if (item.type === 'characterData') {
                translateText(item.target);
            } else {
                translateTree(item.target);
            }
        }
    }

    function schedule(item) {
        pending.push(item);
        if (!scheduled) {
            scheduled = true;
            window.requestAnimationFrame(flush);
        }
    }

    function observe() {
        var observer = new MutationObserver(function (mutations) {
            if (!dictionary) {
                return;
            }
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (mutation.type === 'childList') {
                    for (var j = 0; j < mutation.addedNodes.length; j++) {
                        schedule({ type: 'tree', target: mutation.addedNodes[j] });
                    }
                } else {
                    schedule({ type: mutation.type, target: mutation.target });
                }
            }
        });
        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ATTRIBUTES,
        });
    }

    function apply(words, language) {
        // The keys are stored already normalized in the dictionary file.
        dictionary = words;
        document.documentElement.setAttribute('lang', language);
        translateTree(document.documentElement);
    }

    function start() {
        var language = currentLanguage();
        window.PterodactylLanguage = language;
        if (!language || language === 'en' || !/^[a-z]{2}$/.test(language)) {
            return;
        }

        observe();

        // What was fetched last time is used at once, so the page does not flash in English.
        var cached = read(STORAGE_DICTIONARY + language);
        if (cached) {
            try {
                var words = JSON.parse(cached);
                var ready = function () {
                    apply(words, language);
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', ready);
                } else {
                    ready();
                }
            } catch (e) {
                /* a damaged copy is replaced below */
            }
        }

        var request = new XMLHttpRequest();
        request.open('GET', '/locales/ui.json?locale=' + encodeURIComponent(language));
        request.onload = function () {
            if (request.status !== 200) {
                return;
            }
            var fresh;
            try {
                fresh = JSON.parse(request.responseText);
            } catch (e) {
                return;
            }
            write(STORAGE_DICTIONARY + language, request.responseText);
            var run = function () {
                apply(fresh, language);
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run);
            } else {
                run();
            }
        };
        request.send();
    }

    /**
     * Picks the language of a visitor who is not signed in. Signed-in accounts change theirs on their account page.
     */
    window.PterodactylSetLanguage = function (language) {
        if (/^[a-z]{2}$/.test(language)) {
            write(STORAGE_LANGUAGE, language);
            window.location.reload();
        }
    };

    start();
})();
