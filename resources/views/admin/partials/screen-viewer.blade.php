{{--
    The window in which a member of the staff watches the screen of a person who agreed to share it. It is opened with
    window.pdScreen.open(userId, username) (see the eye buttons). Only put on the pages of staff who can manage users.
    The picture comes straight from the browser of the person (WebRTC); the panel only carries the messages that the
    two browsers need to find each other.
--}}
@once
<div class="pd-screen" id="pd-screen" hidden
     data-base="{{ url('/admin/screen') }}" data-token="{{ csrf_token() }}">
    <div class="pd-screen-box" role="dialog" aria-label="Screen">
        <div class="pd-screen-head">
            <h3><i class="fa fa-eye"></i> <span id="pd-screen-title"></span></h3>
            <span class="pd-screen-actions">
                <button type="button" class="btn btn-default btn-xs" id="pd-screen-full"><i class="fa fa-expand"></i> <span>Full screen</span></button>
                <button type="button" class="btn btn-danger btn-xs" id="pd-screen-stop"><i class="fa fa-times"></i> <span>Stop</span></button>
            </span>
        </div>
        <div class="pd-screen-stage">
            <video id="pd-screen-video" autoplay playsinline muted></video>
            <p class="pd-screen-status" id="pd-screen-status"></p>
        </div>
        <p class="pd-screen-note"><span>The person accepted and can stop at any time. Nothing is recorded.</span></p>
    </div>
</div>
<script>
    (function () {
        var root = document.getElementById('pd-screen');
        var video = document.getElementById('pd-screen-video');
        var status = document.getElementById('pd-screen-status');
        var title = document.getElementById('pd-screen-title');
        var base = root.getAttribute('data-base');
        var token = root.getAttribute('data-token');
        var ICE = [{ urls: 'stun:stun.l.google.com:19302' }];
        var current = null;

        function request(method, url, body) {
            var options = {
                method: method,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }
            };
            if (body) { options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(body); }

            return fetch(url, options).then(function (response) {
                if (response.status === 204) { return {}; }
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (!response.ok) { throw new Error((data && (data.message || (data.errors && data.errors[0] && data.errors[0].detail))) || 'Error'); }
                    return data;
                });
            });
        }

        function say(text, kind) {
            status.textContent = '';
            var span = document.createElement('span');
            span.textContent = text;
            status.appendChild(span);
            status.className = 'pd-screen-status' + (kind ? ' pd-screen-status--' + kind : '');
            status.style.display = text ? '' : 'none';
        }

        function gathered(pc) {
            return new Promise(function (resolve) {
                if (pc.iceGatheringState === 'complete') { resolve(); return; }
                function done() {
                    if (pc.iceGatheringState === 'complete') { pc.removeEventListener('icegatheringstatechange', done); resolve(); }
                }
                pc.addEventListener('icegatheringstatechange', done);
                window.setTimeout(resolve, 4000);
            });
        }

        function teardown() {
            if (!current) { return; }
            window.clearInterval(current.timer);
            if (current.pc) { current.pc.close(); }
            video.srcObject = null;
            current = null;
        }

        // The window stays open with the reason, so that nothing disappears without a word.
        function end(text) {
            var name = current && current.name;
            teardown();
            video.style.display = 'none';
            say(text, 'end');
            if (name) { title.textContent = name; }
        }

        function connect(session) {
            var pc = new RTCPeerConnection({ iceServers: ICE });
            current.pc = pc;
            pc.ontrack = function (event) {
                video.srcObject = event.streams[0];
                video.style.display = '';
                say('');
            };
            pc.onconnectionstatechange = function () {
                if (pc.connectionState === 'failed') { say('The connection could not be made (some networks block it).', 'end'); }
                if (pc.connectionState === 'disconnected') { say('The connection is lost...', 'wait'); }
            };
            pc.setRemoteDescription({ type: 'offer', sdp: session.offer })
                .then(function () { return pc.createAnswer(); })
                .then(function (answer) { return pc.setLocalDescription(answer); })
                .then(function () { return gathered(pc); })
                .then(function () { return request('POST', base + '/' + current.id + '/answer', { id: session.id, sdp: pc.localDescription.sdp }); })
                .catch(function (e) { end(e.message || 'Error'); });
        }

        function poll() {
            if (!current) { return; }
            var mine = current;
            request('GET', base + '/' + mine.id).then(function (body) {
                if (current !== mine) { return; }
                var session = body.data;
                if (!session) { end('The person stopped sharing.'); return; }
                if (session.state === 'declined') { end('The person declined.'); return; }
                if (session.state === 'stopped') { end('The person stopped sharing.'); return; }
                if (session.state === 'requested') {
                    if (Date.now() - mine.started > 125000) { end('No answer from the person.'); return; }
                    say('Waiting for the person to accept...', 'wait');
                    return;
                }
                if (session.state === 'offered' && !mine.pc) { say('Connecting...', 'wait'); connect(session); return; }
                if (mine.pc && !session.alive && session.state === 'connected') { say('The person is not on the page any more...', 'wait'); }
            }).catch(function () { /* the next try will do */ });
        }

        function open(id, name) {
            teardown();
            root.hidden = false;
            title.textContent = name;
            video.style.display = 'none';
            say('Asking...', 'wait');
            current = { id: id, name: name, pc: null, started: Date.now(), timer: null };
            var mine = current;
            request('POST', base + '/' + id).then(function () {
                if (current !== mine) { return; }
                mine.timer = window.setInterval(poll, 1500);
                poll();
            }).catch(function (e) { end(e.message || 'Error'); });
        }

        function close() {
            if (current) { request('DELETE', base + '/' + current.id).catch(function () { /* over anyway */ }); }
            teardown();
            video.style.display = 'none';
            root.hidden = true;
        }

        document.getElementById('pd-screen-stop').addEventListener('click', close);
        document.getElementById('pd-screen-full').addEventListener('click', function () {
            if (video.requestFullscreen) { video.requestFullscreen(); }
        });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !root.hidden && !document.fullscreenElement) { close(); } });

        window.pdScreen = { open: open };
    })();
</script>
@endonce
