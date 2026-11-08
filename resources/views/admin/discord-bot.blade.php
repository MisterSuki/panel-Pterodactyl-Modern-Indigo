@extends('layouts.admin')

@section('title')
    Discord Bot
@endsection

@section('content-header')
    <h1>Discord Bot<small>Your clients drive their servers from Discord.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Discord Bot</li>
    </ol>
@endsection

@section('content')
<style>
    .pd-bot-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 6px 0 0; }
    .pd-bot-steps { margin: 0; padding-left: 20px; }
    .pd-bot-steps li { margin-bottom: 8px; }
    .pd-bot-commands code { display: inline-block; margin: 0 4px 6px 0; }
</style>

<div class="row">
    <div class="col-md-7">
        <form action="{{ route('admin.discord-bot') }}" method="POST">
            {!! csrf_field() !!}
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Application</h3>
                    <div class="box-tools">
                        @if ($configured)
                            <span class="label label-success">Ready</span>
                        @else
                            <span class="label label-default">Not set up</span>
                        @endif
                    </div>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pdBotId">Application ID</label>
                        <input type="text" id="pdBotId" name="application_id" class="form-control" value="{{ old('application_id', $applicationId) }}" autocomplete="off" required>
                        <p class="pd-bot-help">General Information page of your app. It can be the same app as the Discord login of the panel.</p>
                    </div>
                    <div class="form-group">
                        <label for="pdBotKey">Public Key</label>
                        <input type="text" id="pdBotKey" name="public_key" class="form-control" value="{{ old('public_key', $publicKey) }}" autocomplete="off" required>
                        <p class="pd-bot-help">General Information page, under the Application ID. The panel checks with it that a command really comes from Discord.</p>
                    </div>
                    <div class="form-group">
                        <label for="pdBotToken">Bot Token</label>
                        <input type="password" id="pdBotToken" name="token" class="form-control" value="" autocomplete="new-password" placeholder="{{ $tokenSaved ? 'Saved — leave empty to keep it' : '' }}">
                        <p class="pd-bot-help">Bot page → Reset Token. It is stored encrypted.</p>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Interactions Endpoint URL</label>
                        <input type="text" class="form-control" value="{{ $endpoint }}" readonly onclick="this.select()">
                        <p class="pd-bot-help">Paste it in General Information → Interactions Endpoint URL, then save there: Discord checks it at once, so save the keys here first. It must be in https.</p>
                    </div>
                </div>
                <div class="box-footer"><button type="submit" class="btn btn-primary btn-sm">Save</button></div>
            </div>
        </form>
    </div>

    <div class="col-md-5">
        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title">Set it up</h3></div>
            <div class="box-body">
                <ol class="pd-bot-steps">
                    <li>In the <a href="https://discord.com/developers/applications" target="_blank" rel="noopener">Discord Developer Portal</a>, open your app (or create one).</li>
                    <li>Fill in the three fields here, and save.</li>
                    <li>Paste the Interactions Endpoint URL in Discord, and save there.</li>
                    <li>Send the commands, below.</li>
                    @if ($invite)
                        <li><a href="{{ $invite }}" target="_blank" rel="noopener">Invite the bot</a> to your Discord server: it can only write in private to people who share a server with it.</li>
                    @endif
                    <li>Your clients link their Discord on the Account page of the panel.</li>
                </ol>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.discord-bot.commands') }}" method="POST" style="display: inline;">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success btn-sm" @disabled(!$configured)><i class="fa fa-upload"></i> <span>Send the commands to Discord</span></button>
                </form>
                <form action="{{ route('admin.discord-bot.test') }}" method="POST" style="display: inline;">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-default btn-sm" @disabled(!$configured)><i class="fa fa-paper-plane"></i> <span>Send me a test message</span></button>
                </form>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">Commands</h3></div>
            <div class="box-body pd-bot-commands">
                <p><code>/servers</code> <code>/status</code> <code>/start</code> <code>/restart</code> <code>/stop</code></p>
                <p class="pd-bot-help" style="margin: 0;">Each person only sees their own servers and the ones shared with them, with the rights they have in the panel. The answers are only visible to them. The alerts of a server can also come as private messages: Settings tab of the server → Alerts.</p>
            </div>
        </div>
    </div>
</div>
@endsection
