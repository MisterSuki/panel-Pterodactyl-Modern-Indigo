@extends('layouts.admin')

@section('title')
    AI assistant
@endsection

@section('content-header')
    <h1>AI assistant<small>Helps your clients with the bugs of their servers.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">AI assistant</li>
    </ol>
@endsection

@section('content')
<style>
    .pd-ai-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 6px 0 0; }
    .pd-ai-steps { margin: 0; padding-left: 20px; }
    .pd-ai-steps li { margin-bottom: 8px; }
</style>

<div class="row">
    <div class="col-md-7">
        <form action="{{ route('admin.assistant') }}" method="POST">
            {!! csrf_field() !!}
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Settings</h3>
                    <div class="box-tools">
                        @if ($enabled && $keySource)
                            <span class="label label-success">On</span>
                        @elseif ($enabled)
                            <span class="label label-warning">No key</span>
                        @else
                            <span class="label label-default">Off</span>
                        @endif
                    </div>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" id="pdAiEnabled" name="enabled" value="1" @checked($enabled)>
                            <label for="pdAiEnabled">Turn the AI assistant on</label>
                        </div>
                        <p class="pd-ai-help">A button "AI assistant" appears in the console of the servers. It reads the end of the console and the settings of the server (passwords, keys and licences are hidden) to explain errors and give the steps to fix them.</p>
                    </div>
                    <div class="form-group">
                        <label for="pdAiKey">Groq API key</label>
                        <input type="password" id="pdAiKey" name="key" class="form-control" value="" autocomplete="new-password"
                            placeholder="{{ $keySource === 'panel' ? 'Saved — leave empty to keep it' : ($keySource === 'env' ? 'Taken from GROQ_API_KEY in .env' : 'gsk_...') }}">
                        <p class="pd-ai-help">
                            <span>Create it on</span> <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com/keys</a>.
                            <span>It is stored encrypted. Or put GROQ_API_KEY=... in the .env file of the panel instead.</span>
                        </p>
                        @if ($keySource === 'panel')
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input type="hidden" name="forget_key" value="0">
                                <input type="checkbox" id="pdAiForget" name="forget_key" value="1">
                                <label for="pdAiForget">Delete the saved key</label>
                            </div>
                        @endif
                    </div>
                    <div class="form-group">
                        <label for="pdAiModel">Model</label>
                        <input type="text" id="pdAiModel" name="model" class="form-control" value="{{ old('model', $model) }}" list="pdAiModels" autocomplete="off" required>
                        <datalist id="pdAiModels">
                            @foreach ($models as $suggested)
                                <option value="{{ $suggested }}"></option>
                            @endforeach
                        </datalist>
                        <p class="pd-ai-help">The models of Groq are listed on console.groq.com/docs/models. openai/gpt-oss-120b reasons well on errors; llama-3.3-70b-versatile answers faster.</p>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="pdAiAudience">Who can use it</label>
                            <select id="pdAiAudience" name="audience" class="form-control">
                                <option value="all" @selected($audience === 'all')>Everyone (on the servers they can see)</option>
                                <option value="staff" @selected($audience === 'staff')>The administration only</option>
                            </select>
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="pdAiDaily">Questions per person per day</label>
                            <input type="number" id="pdAiDaily" name="daily" class="form-control" min="0" max="10000" value="{{ old('daily', $daily) }}">
                            <p class="pd-ai-help">0 = no limit. Groq has its own limits too.</p>
                        </div>
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
                <ol class="pd-ai-steps">
                    <li>Create a key on <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com</a>.</li>
                    <li>Paste it here (or in the .env of the panel), turn the assistant on, and save.</li>
                    <li>Check it with the test below.</li>
                    <li>Your clients open the console of a server and click "AI assistant".</li>
                </ol>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.assistant.test') }}" method="POST" style="display: inline;">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success btn-sm" @disabled(!$keySource)><i class="fa fa-flask"></i> <span>Test the key and the model</span></button>
                </form>
            </div>
        </div>
        <div class="box">
            <div class="box-header with-border"><h3 class="box-title">What is sent to Groq</h3></div>
            <div class="box-body">
                <p class="pd-ai-help" style="margin: 0;">For each question: the game and the egg of the server, its Docker image, its start command and variables (secret values hidden), the names of the files at its root, and the last 200 lines of its console (keys, tokens and passwords that look like one are hidden). Nothing is kept by the panel.</p>
            </div>
        </div>
    </div>
</div>
@endsection
