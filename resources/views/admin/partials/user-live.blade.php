{{--
    What a person is doing on the panel right now, for the staff who help them: where they are and the latest things
    they did. Needs $liveUser (the person) and $live (the first state, see UserLiveService). It looks again every few
    seconds by itself.
--}}
@php $canWatch = auth()->user()->hasAdminPermission('users.manage') && auth()->id() !== $liveUser->id; @endphp
<div class="pd-card" id="pd-live" data-url="{{ route('admin.presence.person', $liveUser->id, false) }}">
    <div class="pd-card-head">
        <h3><i class="fa fa-eye"></i> <span>What they are doing</span></h3>
        <span class="pd-card-tools">
            @if ($canWatch)
                <button type="button" class="pd-eye" id="pd-live-watch" data-id="{{ $liveUser->id }}" data-name="{{ $liveUser->username }}" title="Ask to see their screen"><i class="fa fa-eye"></i> <span>See their screen</span></button>
            @endif
            <span class="pd-live-note"><i class="fa fa-circle pd-live"></i> <span>Live</span></span>
        </span>
    </div>
    <div class="pd-live-who" id="pd-live-who"></div>
    <ul class="pd-live-actions" id="pd-live-actions"></ul>
    <p class="pd-hint" id="pd-live-empty" style="display:none"><span>Nothing done yet.</span></p>
</div>
@if ($canWatch)
    @include('admin.partials.screen-viewer')
@endif
<script>
    (function () {
        var watch = document.getElementById('pd-live-watch');
        if (watch) {
            watch.addEventListener('click', function () { window.pdScreen.open(parseInt(watch.getAttribute('data-id'), 10), watch.getAttribute('data-name')); });
        }
        var card = document.getElementById('pd-live');
        var who = document.getElementById('pd-live-who');
        var list = document.getElementById('pd-live-actions');
        var empty = document.getElementById('pd-live-empty');
        var pages = {
            dashboard: 'Dashboard', console: 'Console', files: 'Files', databases: 'Databases', schedules: 'Schedules',
            users: 'Users', backups: 'Backups', network: 'Network', startup: 'Startup', settings: 'Settings',
            activity: 'Activity', account: 'Account', tickets: 'Support', admin: 'Administration', server: 'Server',
            panel: 'On the panel'
        };
        var state = @json($live);
        var fetchedAt = Date.now();

        function el(tag, className, text) {
            var node = document.createElement(tag);
            if (className) { node.className = className; }
            if (text !== undefined) { node.textContent = text; }
            return node;
        }

        // "Just now", "42 s", "3 min", "2 h" or "5 d": the number and the unit are apart, so the unit can be translated.
        function age(seconds) {
            var time = el('time', 'pd-age');
            if (seconds < 10) {
                time.appendChild(el('span', null, 'Just now'));
                return time;
            }
            var value = seconds, unit = 's';
            if (seconds >= 86400) { value = Math.floor(seconds / 86400); unit = 'd'; }
            else if (seconds >= 3600) { value = Math.floor(seconds / 3600); unit = 'h'; }
            else if (seconds >= 60) { value = Math.floor(seconds / 60); unit = 'min'; }
            time.appendChild(document.createTextNode(value + ' '));
            time.appendChild(el('span', null, unit));
            return time;
        }

        function render() {
            var elapsed = Math.round((Date.now() - fetchedAt) / 1000);
            who.textContent = '';
            var img = el('img', 'pd-avatar');
            img.src = state.user.avatar;
            img.alt = '';
            img.width = 40;
            img.height = 40;
            who.appendChild(img);

            var text = el('div', 'pd-live-who-text');
            text.appendChild(el('strong', null, state.user.username));
            var line = el('small');
            if (state.online) {
                line.appendChild(el('i', 'fa fa-circle pd-dot pd-dot--on'));
                line.appendChild(el('span', null, 'Online'));
                line.appendChild(document.createTextNode(' · '));
                line.appendChild(el('span', null, pages[state.page] || pages.panel));
                if (state.server) { line.appendChild(document.createTextNode(' · ' + state.server)); }
            } else {
                line.appendChild(el('i', 'fa fa-circle pd-dot'));
                line.appendChild(el('span', null, 'Not on the panel'));
                if (state.seconds !== null) {
                    line.appendChild(document.createTextNode(' · '));
                    line.appendChild(age(state.seconds + elapsed));
                }
            }
            text.appendChild(line);
            who.appendChild(text);

            list.textContent = '';
            state.activity.forEach(function (entry) {
                var item = el('li');
                var main = el('span', 'pd-live-what');
                main.appendChild(document.createTextNode(entry.text));
                if (entry.server) { main.appendChild(el('small', null, entry.server)); }
                item.appendChild(main);
                item.appendChild(age(entry.seconds + elapsed));
                list.appendChild(item);
            });
            empty.style.display = state.activity.length === 0 ? '' : 'none';
        }

        function refresh() {
            if (document.hidden) { return; }
            fetch(card.getAttribute('data-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (body) { if (body && body.user) { state = body; fetchedAt = Date.now(); render(); } })
                .catch(function () { /* the next try will do */ });
        }

        render();
        window.setInterval(refresh, 5000);
        // Between two answers the ages go on counting.
        window.setInterval(render, 1000);
        document.addEventListener('visibilitychange', refresh);
    })();
</script>
