@extends('layouts.admin')

@section('title')
    Ticket #{{ $ticket->id }}
@endsection

@section('content-header')
    <h1>{{ $ticket->subject }}<small>Ticket #{{ $ticket->id }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.tickets') }}">Tickets</a></li>
        <li class="active">#{{ $ticket->id }}</li>
    </ol>
@endsection

@section('content')
@php
    $statusLabels = ['open' => 'Waiting for the staff', 'answered' => 'Answered', 'closed' => 'Closed'];
    $priorityLabels = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    $categoryLabels = ['general' => 'General', 'technical' => 'Technical', 'billing' => 'Billing', 'other' => 'Other'];
@endphp
<div class="row">
    <div class="col-md-8">
        <div class="pd-card pd-chat-card" id="pd-ticket"
             data-messages="{{ route('admin.tickets.messages', $ticket->id, false) }}"
             data-reply="{{ route('admin.tickets.reply', $ticket->id, false) }}"
             data-update="{{ route('admin.tickets.update', $ticket->id, false) }}"
             data-me="{{ auth()->id() }}" data-token="{{ csrf_token() }}">
            <div class="pd-card-head">
                <h3><i class="fa fa-comments"></i> <span>Conversation</span></h3>
                <span class="pd-live-note"><i class="fa fa-circle pd-live"></i> <span>Live</span></span>
            </div>
            <div class="pd-chat" id="pd-chat"></div>
            @if ($canManage)
            <form class="pd-composer" id="pd-composer">
                <textarea id="pd-body" rows="3" maxlength="5000" placeholder="Write your answer..."></textarea>
                <div class="pd-composer-bar">
                    <label class="pd-note-toggle"><input type="checkbox" id="pd-internal"> <span>Internal note (the user does not see it)</span></label>
                    <span class="pd-error" id="pd-error"></span>
                    <button type="submit" class="btn btn-primary btn-sm" id="pd-send"><i class="fa fa-paper-plane"></i> <span>Send</span></button>
                </div>
            </form>
            <p class="pd-hint" id="pd-closed" style="display:none"><span>This ticket is closed. Reopen it to write in it.</span></p>
            @endif
        </div>
    </div>
    <div class="col-md-4">
        <div class="pd-card">
            <div class="pd-card-head"><h3><i class="fa fa-info-circle"></i> <span>Details</span></h3><span class="pd-pill pd-status-{{ $ticket->status }}" id="pd-status-pill"><span>{{ $statusLabels[$ticket->status] }}</span></span></div>
            <dl class="pd-details">
                <dt>User</dt><dd><a href="{{ route('admin.users.view', $ticket->user_id) }}">{{ $ticket->user->username }}</a><small>{{ $ticket->user->email }}</small></dd>
                @if ($ticket->server)<dt>Server</dt><dd><a href="{{ route('admin.servers.view', $ticket->server_id) }}">{{ $ticket->server->name }}</a></dd>@endif
                <dt>Category</dt><dd><span>{{ $categoryLabels[$ticket->category] ?? $ticket->category }}</span></dd>
                <dt>Opened on</dt><dd>{{ $ticket->created_at->format('Y-m-d H:i') }}</dd>
                @if ($ticket->closed_at)<dt>Closed on</dt><dd>{{ $ticket->closed_at->format('Y-m-d H:i') }}</dd>@endif
            </dl>
            @if ($canManage)
            <div class="pd-controls">
                <label for="pd-priority"><span>Priority</span></label>
                <select id="pd-priority" class="form-control">
                    @foreach ($priorityLabels as $value => $label)
                        <option value="{{ $value }}" @if ($ticket->priority === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="pd-assignee"><span>Handled by</span></label>
                <select id="pd-assignee" class="form-control">
                    <option value="">Nobody</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @if ($ticket->assigned_to === $member->id) selected @endif>{{ $member->username }}</option>
                    @endforeach
                </select>
                <div class="pd-control-row">
                    <button type="button" class="btn btn-default btn-sm" id="pd-take"><i class="fa fa-hand-paper-o"></i> <span>Take it</span></button>
                    <button type="button" class="btn btn-danger btn-sm" id="pd-close" @if ($ticket->status === 'closed') style="display:none" @endif><i class="fa fa-lock"></i> <span>Close the ticket</span></button>
                    <button type="button" class="btn btn-success btn-sm" id="pd-reopen" @if ($ticket->status !== 'closed') style="display:none" @endif><i class="fa fa-unlock"></i> <span>Reopen</span></button>
                </div>
            </div>
            @endif
        </div>
        <div class="pd-card">
            <div class="pd-card-head"><h3><i class="fa fa-file-text-o"></i> <span>Transcript</span></h3></div>
            <div class="pd-actions">
                <a href="{{ route('admin.tickets.transcript', $ticket->id) }}"><i class="fa fa-download"></i> <span>Download (text, with the notes)</span></a>
                <a href="{{ route('admin.tickets.transcript', ['id' => $ticket->id, 'format' => 'html']) }}"><i class="fa fa-print"></i> <span>Download (page to print, with the notes)</span></a>
                <a href="{{ route('admin.tickets.transcript', ['id' => $ticket->id, 'public' => 1]) }}"><i class="fa fa-user"></i> <span>Download what the user sees</span></a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var root = document.getElementById('pd-ticket');
            var chat = document.getElementById('pd-chat');
            var me = String(root.getAttribute('data-me'));
            var token = root.getAttribute('data-token');
            var last = 0;
            var closed = @json($ticket->status === 'closed');
            var locale = document.documentElement.getAttribute('lang') || undefined;

            var labels = { open: 'Waiting for the staff', answered: 'Answered', closed: 'Closed' };
            var statusPill = document.getElementById('pd-status-pill');

            function el(tag, className, text) {
                var node = document.createElement(tag);
                if (className) { node.className = className; }
                if (text !== undefined) { node.textContent = text; }
                return node;
            }

            function when(iso) {
                var date = new Date(iso);
                return isNaN(date) ? '' : date.toLocaleString(locale, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
            }

            function bubble(message) {
                var kind = message.internal ? 'pd-msg--note' : (message.staff ? 'pd-msg--staff' : 'pd-msg--user');
                var row = el('div', 'pd-msg ' + kind);
                var meta = el('div', 'pd-msg-meta');
                meta.appendChild(el('strong', null, message.author));
                if (message.internal) { meta.appendChild(el('span', 'pd-tag', 'Internal note')); }
                else if (message.staff) { meta.appendChild(el('span', 'pd-tag pd-tag--staff', 'Staff')); }
                meta.appendChild(el('time', null, when(message.at)));
                row.appendChild(meta);
                row.appendChild(el('div', 'pd-msg-body', message.body));
                row.setAttribute('data-id', message.id);

                return row;
            }

            function add(messages) {
                var nearBottom = chat.scrollHeight - chat.scrollTop - chat.clientHeight < 80;
                messages.forEach(function (message) {
                    if (message.id > last) { chat.appendChild(bubble(message)); last = message.id; }
                });
                if (nearBottom || messages.length && messages[messages.length - 1].mine) { chat.scrollTop = chat.scrollHeight; }
            }

            function applyTicket(ticket) {
                if (!ticket) { return; }
                closed = ticket.status === 'closed';
                statusPill.className = 'pd-pill pd-status-' + ticket.status;
                statusPill.textContent = '';
                statusPill.appendChild(el('span', null, labels[ticket.status] || ticket.status));
                var composer = document.getElementById('pd-composer');
                var note = document.getElementById('pd-closed');
                if (composer) { composer.style.display = closed ? 'none' : ''; }
                if (note) { note.style.display = closed ? '' : 'none'; }
                var closeButton = document.getElementById('pd-close');
                var reopenButton = document.getElementById('pd-reopen');
                if (closeButton) { closeButton.style.display = closed ? 'none' : ''; }
                if (reopenButton) { reopenButton.style.display = closed ? '' : 'none'; }
                var priority = document.getElementById('pd-priority');
                if (priority && document.activeElement !== priority) { priority.value = ticket.priority; }
                var assignee = document.getElementById('pd-assignee');
                if (assignee && document.activeElement !== assignee) { assignee.value = ticket.assigned_to || ''; }
            }

            function request(url, options) {
                options = options || {};
                options.credentials = 'same-origin';
                options.headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }, options.headers || {});

                return fetch(url, options).then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (body) {
                        if (!response.ok) { throw new Error((body && (body.message || (body.errors && body.errors[0] && body.errors[0].detail))) || 'Error'); }
                        return body;
                    });
                });
            }

            function post(url, data) {
                return request(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            }

            function poll() {
                if (document.hidden) { return; }
                request(root.getAttribute('data-messages') + '?after=' + last)
                    .then(function (body) { add(body.data || []); applyTicket(body.ticket); })
                    .catch(function () { /* the next try will do */ });
            }

            add(@json($messages));
            chat.scrollTop = chat.scrollHeight;
            applyTicket({ status: @json($ticket->status), priority: @json($ticket->priority), assigned_to: @json($ticket->assigned_to) });
            window.setInterval(poll, 2000);
            document.addEventListener('visibilitychange', poll);

            var composer = document.getElementById('pd-composer');
            if (!composer) { return; }
            var body = document.getElementById('pd-body');
            var error = document.getElementById('pd-error');
            var send = document.getElementById('pd-send');

            composer.addEventListener('submit', function (event) {
                event.preventDefault();
                var text = body.value.trim();
                if (text === '' || send.disabled) { return; }
                send.disabled = true;
                error.textContent = '';
                post(root.getAttribute('data-reply'), { message: text, internal: document.getElementById('pd-internal').checked })
                    .then(function (result) { body.value = ''; add([result.attributes]); applyTicket(result.ticket); })
                    .catch(function (e) { error.textContent = e.message; })
                    .then(function () { send.disabled = false; body.focus(); });
            });

            // Ctrl + Enter sends.
            body.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) { composer.dispatchEvent(new Event('submit', { cancelable: true })); }
            });

            function update(data) {
                post(root.getAttribute('data-update'), data).then(function (result) { applyTicket(result.ticket); }).catch(function (e) { error.textContent = e.message; });
            }
            document.getElementById('pd-priority').addEventListener('change', function () { update({ priority: this.value }); });
            document.getElementById('pd-assignee').addEventListener('change', function () { update({ assigned_to: this.value === '' ? null : parseInt(this.value, 10) }); });
            document.getElementById('pd-take').addEventListener('click', function () { update({ assigned_to: parseInt(me, 10) }); });
            document.getElementById('pd-close').addEventListener('click', function () { update({ status: 'closed' }); });
            document.getElementById('pd-reopen').addEventListener('click', function () { update({ status: 'open' }); });
        })();
    </script>
@endsection
