// The search of the whole administration: Ctrl+K (or /) opens it anywhere, the arrows choose, Enter opens.
(function () {
    'use strict';

    var url = document.documentElement.getAttribute('data-pd-search');
    if (!url) return;

    var box, input, list, items = [], active = -1, timer = 0, last = '';

    function esc(text) {
        return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function build() {
        box = document.createElement('div');
        box.className = 'pd-search';
        box.hidden = true;
        box.innerHTML =
            '<div class="pd-search-panel" role="dialog" aria-label="Search">' +
            '<div class="pd-search-field"><i class="fa fa-search"></i>' +
            '<input type="text" autocomplete="off" spellcheck="false" placeholder="Search a server, a user, a node, an IP or a domain">' +
            '<kbd>Esc</kbd></div>' +
            '<div class="pd-search-list" role="listbox"></div>' +
            '<div class="pd-search-foot"><span><kbd>↑</kbd><kbd>↓</kbd> <span>to choose</span></span><span><kbd>Enter</kbd> <span>to open</span></span></div>' +
            '</div>';
        document.body.appendChild(box);
        input = box.querySelector('input');
        list = box.querySelector('.pd-search-list');

        box.addEventListener('mousedown', function (e) {
            if (e.target === box) close();
        });
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(search, 150);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!items.length) return;
                select((active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);
            } else if (e.key === 'Enter' && items[active]) {
                window.location.href = items[active].getAttribute('href');
            } else if (e.key === 'Escape') {
                close();
            }
        });
    }

    function select(index) {
        items.forEach(function (el, i) { el.classList.toggle('is-active', i === index); });
        active = index;
        if (items[index]) items[index].scrollIntoView({ block: 'nearest' });
    }

    function render(groups) {
        if (!groups.length) {
            list.innerHTML = '<p class="pd-search-empty">' + (input.value.trim().length < 2 ? 'Type at least two characters.' : 'Nothing found.') + '</p>';
            items = [];
            active = -1;
            return;
        }
        list.innerHTML = groups.map(function (group) {
            return '<p class="pd-search-group">' + esc(group.label) + '</p>' + group.items.map(function (item) {
                return '<a class="pd-search-item" role="option" href="' + esc(item.url) + '">' +
                    '<i class="fa ' + esc(item.icon) + '"></i><span><b>' + esc(item.title) + '</b>' +
                    '<small>' + esc(item.subtitle) + '</small></span>' +
                    (item.badge ? '<em>' + esc(item.badge) + '</em>' : '') + '</a>';
            }).join('');
        }).join('');
        items = Array.prototype.slice.call(list.querySelectorAll('.pd-search-item'));
        items.forEach(function (el, i) {
            el.addEventListener('mousemove', function () { if (active !== i) select(i); });
        });
        select(0);
    }

    function search() {
        var q = input.value.trim();
        if (q === last) return;
        last = q;
        if (q.length < 2) return render([]);
        fetch(url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { groups: [] }; })
            .then(function (data) { if (q === input.value.trim()) render(data.groups || []); })
            .catch(function () {});
    }

    function open() {
        if (!box) build();
        box.hidden = false;
        document.body.classList.add('pd-search-open');
        input.value = '';
        last = '';
        render([]);
        setTimeout(function () { input.focus(); }, 0);
    }

    function close() {
        if (!box) return;
        box.hidden = true;
        document.body.classList.remove('pd-search-open');
    }

    document.addEventListener('keydown', function (e) {
        var typing = /input|textarea|select/i.test(e.target.tagName) || e.target.isContentEditable;
        if ((e.key === 'k' || e.key === 'K') && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            box && !box.hidden ? close() : open();
        } else if (e.key === '/' && !typing) {
            e.preventDefault();
            open();
        }
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('[data-pd-search-open]')) {
            e.preventDefault();
            open();
        }
    });
})();
