<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Pterodactyl\Models\User;
use Illuminate\Http\Request;
use Pterodactyl\Models\Ticket;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Admin\UserLiveService;
use Pterodactyl\Services\Tickets\TicketService;
use Pterodactyl\Services\Tickets\TicketTranscript;

/**
 * The tickets, seen by the staff: the list, a conversation that is followed live, the answers and the internal notes,
 * who takes care of it, and the transcript. Reading is for the staff who may see tickets, anything that changes
 * something needs the right to manage them (see the "admin.can:tickets" of the routes).
 */
class TicketController extends Controller
{
    public function __construct(private TicketService $tickets, private TicketTranscript $transcript)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'active');
        $query = Ticket::query()->with(['user:id,username', 'assignee:id,username', 'server'])
            ->when($status === 'active', fn ($q) => $q->whereIn('status', [Ticket::OPEN, Ticket::ANSWERED]))
            ->when(in_array($status, Ticket::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when(in_array((string) $request->query('priority'), Ticket::PRIORITIES, true), fn ($q) => $q->where('priority', $request->query('priority')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when(trim((string) $request->query('q')) !== '', function ($q) use ($request) {
                $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->query('q'))) . '%';
                $q->where(fn ($inner) => $inner->where('subject', 'like', $term)->orWhereHas('user', fn ($u) => $u->where('username', 'like', $term)));
            })
            // What waits for the staff comes first, then the most urgent, then the oldest question.
            ->orderByRaw("case status when 'open' then 0 when 'answered' then 1 else 2 end")
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
            ->orderBy('last_message_at');

        $counts = Ticket::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return view('admin.tickets.index', [
            'tickets' => $query->paginate(20)->withQueryString(),
            'counts' => [
                'active' => (int) ($counts[Ticket::OPEN] ?? 0) + (int) ($counts[Ticket::ANSWERED] ?? 0),
                'open' => (int) ($counts[Ticket::OPEN] ?? 0),
                'answered' => (int) ($counts[Ticket::ANSWERED] ?? 0),
                'closed' => (int) ($counts[Ticket::CLOSED] ?? 0),
            ],
            'status' => $status,
        ]);
    }

    public function view(Request $request, int $id): View
    {
        $ticket = Ticket::query()->with(['user', 'assignee:id,username', 'server'])->findOrFail($id);
        $messages = $this->tickets->messagesAfter($ticket, 0, true);

        return view('admin.tickets.view', [
            'ticket' => $ticket,
            'messages' => $messages->map(fn ($m) => $this->tickets->presentMessage($m, $request->user()->id))->all(),
            'staff' => User::query()->where('root_admin', true)->orWhereNotNull('admin_role_id')->orderBy('username')->get(['id', 'username']),
            'canManage' => $request->user()->hasAdminPermission('tickets.manage'),
            'live' => app(UserLiveService::class)->build($ticket->user),
        ]);
    }

    /**
     * What is new since the message the page has seen, and the state of the ticket.
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $messages = $this->tickets->messagesAfter($ticket, max(0, (int) $request->query('after', 0)), true);

        return new JsonResponse([
            'object' => 'list',
            'ticket' => $this->summary($ticket->fresh()),
            'data' => $messages->map(fn ($m) => $this->tickets->presentMessage($m, $request->user()->id))->all(),
        ]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $data = $request->validate(['message' => 'required|string|max:' . TicketService::MAX_BODY, 'internal' => 'sometimes|boolean']);

        $message = $this->tickets->reply($ticket, $request->user(), $data['message'], true, (bool) ($data['internal'] ?? false));

        return new JsonResponse([
            'object' => 'ticket_message',
            'attributes' => $this->tickets->presentMessage($message->load('author:id,username,uuid,avatar,avatar_updated_at'), $request->user()->id),
            'ticket' => $this->summary($ticket->fresh()),
        ], 201);
    }

    /**
     * The priority, the status and who takes care of the ticket.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $data = $request->validate([
            'priority' => 'sometimes|string',
            'status' => 'sometimes|string',
            'assigned_to' => 'sometimes|nullable|integer',
        ]);

        $ticket = $this->tickets->update($ticket, $request->user(), $data);

        return new JsonResponse(['object' => 'ticket', 'ticket' => $this->summary($ticket->fresh())]);
    }

    public function transcript(Request $request, int $id): Response
    {
        $ticket = Ticket::query()->findOrFail($id);
        $locale = $request->user()->language ?: config('app.locale');
        $html = $request->query('format') === 'html';
        // The staff get the internal notes too, unless they ask for the version the person can see.
        $forStaff = !$request->boolean('public');
        $body = $html ? $this->transcript->html($ticket, $forStaff, $locale) : $this->transcript->text($ticket, $forStaff, $locale);

        return new Response($body, 200, [
            'Content-Type' => $html ? 'text/html; charset=utf-8' : 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $this->transcript->filename($ticket, $html ? 'html' : 'txt') . '"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Ticket $ticket): array
    {
        $ticket->loadMissing('assignee:id,username');

        return [
            'id' => $ticket->id,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'assigned_to' => $ticket->assigned_to,
            'assignee' => $ticket->assignee?->username,
            'closed_at' => $ticket->closed_at?->toIso8601String(),
        ];
    }
}
