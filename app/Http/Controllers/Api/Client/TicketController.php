<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Pterodactyl\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Pterodactyl\Services\Tickets\TicketService;
use Pterodactyl\Services\Tickets\TicketTranscript;
use Illuminate\Validation\Rule;

/**
 * The tickets of the person who is signed in: they can open one, write in it, close it, reopen it and take the
 * transcript. They never see anyone else's. The conversation is followed by asking for the messages after the last
 * one seen, every couple of seconds.
 */
class TicketController extends ClientApiController
{
    public function __construct(private TicketService $tickets, private TicketTranscript $transcript)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->where('user_id', $request->user()->id)
            ->with('server')
            ->withExists(['messages as unread' => fn ($query) => $query
                ->where('is_staff', true)
                ->where('is_internal', false)
                ->whereColumn('ticket_messages.id', '>', 'tickets.user_read_id')])
            ->orderByRaw("case when status = 'closed' then 1 else 0 end")
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        return new JsonResponse([
            'object' => 'list',
            'data' => $tickets->map(fn (Ticket $ticket) => $this->tickets->presentTicket($ticket, (bool) $ticket->getAttribute('unread')))->all(),
        ]);
    }

    /**
     * How many tickets have an answer that has not been read. The navigation bar asks for it now and then.
     */
    public function unread(Request $request): JsonResponse
    {
        return new JsonResponse(['count' => $this->tickets->unreadCount($request->user())]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|min:3|max:120',
            'category' => ['required', 'string', Rule::in(Ticket::CATEGORIES)],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high'])],
            'server_id' => 'nullable|integer',
            'message' => 'required|string|max:' . TicketService::MAX_BODY,
        ]);

        $ticket = $this->tickets->open(
            $request->user(),
            $data['subject'],
            $data['category'],
            isset($data['server_id']) ? (int) $data['server_id'] : null,
            $data['message'],
            $data['priority'] ?? 'normal'
        );

        return new JsonResponse(['object' => 'ticket', 'attributes' => $this->tickets->presentTicket($ticket)], 201);
    }

    /**
     * The ticket with its whole conversation, which counts as read.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = $this->find($request, $id);
        $messages = $this->tickets->messagesAfter($ticket, 0, false);
        $this->tickets->markRead($ticket, (int) ($messages->last()?->id ?? 0));

        return new JsonResponse([
            'object' => 'ticket',
            'attributes' => $this->tickets->presentTicket($ticket),
            'messages' => $messages->map(fn ($m) => $this->tickets->presentMessage($m, $request->user()->id))->all(),
        ]);
    }

    /**
     * What is new since the message the page has seen, and the state of the ticket.
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $ticket = $this->find($request, $id);
        $messages = $this->tickets->messagesAfter($ticket, max(0, (int) $request->query('after', 0)), false);
        if ($messages->isNotEmpty()) {
            $this->tickets->markRead($ticket, (int) $messages->last()->id);
        }

        return new JsonResponse([
            'object' => 'list',
            'status' => $ticket->status,
            'data' => $messages->map(fn ($m) => $this->tickets->presentMessage($m, $request->user()->id))->all(),
        ]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = $this->find($request, $id);
        $data = $request->validate(['message' => 'required|string|max:' . TicketService::MAX_BODY]);

        $message = $this->tickets->reply($ticket, $request->user(), $data['message'], false);

        return new JsonResponse(['object' => 'ticket_message', 'attributes' => $this->tickets->presentMessage($message->load('author:id,username'), $request->user()->id)], 201);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $ticket = $this->tickets->close($this->find($request, $id), $request->user());

        return new JsonResponse(['object' => 'ticket', 'attributes' => $this->tickets->presentTicket($ticket)]);
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $ticket = $this->tickets->reopen($this->find($request, $id));

        return new JsonResponse(['object' => 'ticket', 'attributes' => $this->tickets->presentTicket($ticket)]);
    }

    /**
     * The transcript, as a text file or as a page that prints (?format=html).
     */
    public function transcript(Request $request, int $id): Response
    {
        $ticket = $this->find($request, $id);
        $locale = $request->user()->language ?: config('app.locale');
        $html = $request->query('format') === 'html';
        $body = $html ? $this->transcript->html($ticket, false, $locale) : $this->transcript->text($ticket, false, $locale);

        return new Response($body, 200, [
            'Content-Type' => $html ? 'text/html; charset=utf-8' : 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $this->transcript->filename($ticket, $html ? 'html' : 'txt') . '"',
        ]);
    }

    /**
     * A ticket of the person, and nobody else's: another person's ticket does not exist for them.
     */
    private function find(Request $request, int $id): Ticket
    {
        return Ticket::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
