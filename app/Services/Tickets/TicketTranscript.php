<?php

namespace Pterodactyl\Services\Tickets;

use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketMessage;
use Illuminate\Support\Collection;

/**
 * The transcript of a ticket: the whole conversation, in a file that can be kept or sent. The person who opened the
 * ticket gets what they could see; the staff can also have the internal notes and the email of the person.
 */
class TicketTranscript
{
    /**
     * The transcript as plain text.
     */
    public function text(Ticket $ticket, bool $forStaff, string $locale = 'en'): string
    {
        $t = fn (string $key, array $replace = []) => trans('tickets.' . $key, $replace, $locale);
        $lines = [];
        $lines[] = $t('transcript_title', ['id' => $ticket->id, 'subject' => $ticket->subject]);
        $lines[] = str_repeat('=', 72);
        foreach ($this->details($ticket, $forStaff, $locale) as $label => $value) {
            $lines[] = $label . ': ' . $value;
        }
        $lines[] = str_repeat('-', 72);
        $lines[] = '';

        foreach ($this->messages($ticket, $forStaff) as $message) {
            $lines[] = '[' . $this->time($message->created_at) . '] ' . $this->author($message, $locale) . ':';
            foreach (explode("\n", $message->body) as $line) {
                $lines[] = '    ' . $line;
            }
            $lines[] = '';
        }

        $lines[] = str_repeat('-', 72);
        $lines[] = $t('transcript_generated', ['date' => $this->time(now())]);

        return implode("\n", $lines) . "\n";
    }

    /**
     * The transcript as a page that prints well (and can be saved as a PDF from the browser).
     */
    public function html(Ticket $ticket, bool $forStaff, string $locale = 'en'): string
    {
        $t = fn (string $key, array $replace = []) => trans('tickets.' . $key, $replace, $locale);
        $details = '';
        foreach ($this->details($ticket, $forStaff, $locale) as $label => $value) {
            $details .= '<tr><th>' . e($label) . '</th><td>' . e($value) . '</td></tr>';
        }

        $messages = '';
        foreach ($this->messages($ticket, $forStaff) as $message) {
            $class = $message->is_internal ? 'note' : ($message->is_staff ? 'staff' : 'user');
            $messages .= '<div class="msg ' . $class . '"><div class="meta"><strong>' . e($this->author($message, $locale)) . '</strong> <span>' . e($this->time($message->created_at)) . '</span></div><div class="body">' . nl2br(e($message->body)) . '</div></div>';
        }

        return '<!DOCTYPE html><html lang="' . e($locale) . '"><head><meta charset="utf-8"><title>' . e($t('transcript_title', ['id' => $ticket->id, 'subject' => $ticket->subject])) . '</title><style>'
            . 'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#1f2937;max-width:780px;margin:32px auto;padding:0 20px;line-height:1.5}'
            . 'h1{font-size:22px;margin:0 0 4px}table{border-collapse:collapse;margin:16px 0 24px;font-size:14px}th{text-align:left;color:#6b7280;font-weight:500;padding:3px 18px 3px 0}td{padding:3px 0}'
            . '.msg{border:1px solid #e5e7eb;border-left-width:4px;border-radius:8px;padding:10px 14px;margin:12px 0;break-inside:avoid}.msg.user{border-left-color:#6366f1}.msg.staff{border-left-color:#16a34a}.msg.note{border-left-color:#f59e0b;background:#fffbeb}'
            . '.meta{font-size:13px;color:#6b7280;margin-bottom:4px}.meta strong{color:#111827;margin-right:8px}.body{white-space:normal;word-wrap:break-word}.foot{margin-top:28px;font-size:12px;color:#9ca3af}'
            . '@media print{body{margin:0}}</style></head><body>'
            . '<h1>' . e($t('transcript_title', ['id' => $ticket->id, 'subject' => $ticket->subject])) . '</h1>'
            . '<table>' . $details . '</table>' . $messages
            . '<div class="foot">' . e($t('transcript_generated', ['date' => $this->time(now())])) . '</div></body></html>';
    }

    public function filename(Ticket $ticket, string $extension): string
    {
        return 'ticket-' . $ticket->id . '-transcript.' . $extension;
    }

    /**
     * @return array<string, string>
     */
    private function details(Ticket $ticket, bool $forStaff, string $locale): array
    {
        $t = fn (string $key) => trans('tickets.' . $key, [], $locale);
        $ticket->loadMissing(['user:id,username,email', 'assignee:id,username', 'server']);

        $details = [
            $t('status') => $t('status_' . $ticket->status),
            $t('priority') => $t('priority_' . $ticket->priority),
            $t('category') => $t('category_' . $ticket->category),
            $t('opened_by') => $ticket->user->username . ($forStaff ? ' (' . $ticket->user->email . ')' : ''),
            $t('opened_on') => $this->time($ticket->created_at),
        ];
        if ($ticket->server) {
            $details[$t('server')] = $ticket->server->name;
        }
        if ($forStaff && $ticket->assignee) {
            $details[$t('assigned_to')] = $ticket->assignee->username;
        }
        if ($ticket->closed_at) {
            $details[$t('closed_on')] = $this->time($ticket->closed_at);
        }

        return $details;
    }

    /**
     * @return Collection<int, TicketMessage>
     */
    private function messages(Ticket $ticket, bool $forStaff): Collection
    {
        return $ticket->messages()
            ->with('author:id,username')
            ->when(!$forStaff, fn ($query) => $query->where('is_internal', false))
            ->get();
    }

    private function author(TicketMessage $message, string $locale): string
    {
        $name = $message->author->username ?? '?';
        if ($message->is_internal) {
            return trans('tickets.author_note', ['name' => $name], $locale);
        }

        return $message->is_staff ? trans('tickets.author_staff', ['name' => $name], $locale) : $name;
    }

    private function time(\DateTimeInterface $date): string
    {
        return \Illuminate\Support\Carbon::instance($date)->setTimezone((string) config('app.timezone', 'UTC'))->format('Y-m-d H:i') . ' ' . config('app.timezone', 'UTC');
    }
}
