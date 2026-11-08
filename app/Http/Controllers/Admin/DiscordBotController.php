<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Discord\DiscordBot;

/**
 * The settings of the Discord bot: the keys of its application, its commands, and a test message.
 */
class DiscordBotController extends Controller
{
    public function __construct(private DiscordBot $bot, private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        $id = $this->bot->applicationId();

        return view('admin.discord-bot', [
            'applicationId' => $id,
            'publicKey' => $this->bot->publicKey(),
            'tokenSaved' => $this->bot->token() !== null,
            'configured' => $this->bot->configured(),
            'endpoint' => route('discord.interactions'),
            'invite' => $id !== '' ? 'https://discord.com/oauth2/authorize?client_id=' . urlencode($id) . '&scope=bot%20applications.commands&permissions=0' : null,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'application_id' => ['required', 'string', 'regex:/^\d{5,25}$/'],
            'public_key' => ['required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'token' => ['nullable', 'string', 'max:200'],
        ]);
        $this->bot->save($data['application_id'], $data['public_key'], $data['token'] ?? null);
        $this->alert->success('The bot was saved.')->flash();

        return redirect()->route('admin.discord-bot');
    }

    public function commands(): RedirectResponse
    {
        try {
            $count = $this->bot->registerCommands();
            $this->alert->success("$count commands were sent to Discord: /servers, /status, /start, /restart and /stop.")->flash();
        } catch (\Throwable $exception) {
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.discord-bot');
    }

    public function test(Request $request): RedirectResponse
    {
        $discordId = (string) $request->user()->discord_id;
        if ($discordId === '') {
            $this->alert->warning('Link your own Discord account on your Account page first.')->flash();
        } elseif ($this->bot->directMessage($discordId, DiscordBot::card('Test of the bot', ['The bot of the panel can write to you.'], 'success'))) {
            $this->alert->success('The message was sent: look at your Discord.')->flash();
        } else {
            $this->alert->danger('Discord refused the message. The bot can only write to someone who shares a Discord server with it: invite it to yours.')->flash();
        }

        return redirect()->route('admin.discord-bot');
    }
}
