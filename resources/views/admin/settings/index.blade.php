@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'basic'])

@section('title')
    Settings
@endsection

@section('content-header')
    <h1>Panel Settings<small>Configure Pterodactyl to your liking.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Panel Settings</h3>
                </div>
                <form action="{{ route('admin.settings') }}" method="POST">
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Company Name</label>
                                <div>
                                    <input type="text" class="form-control" name="app:name" value="{{ old('app:name', config('app.name')) }}" />
                                    <p class="text-muted"><small>This is the name that is used throughout the panel and in emails sent to clients.</small></p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Require 2-Factor Authentication</label>
                                <div>
                                    <div class="btn-group" data-toggle="buttons">
                                        @php
                                            $level = old('pterodactyl:auth:2fa_required', config('pterodactyl.auth.2fa_required'));
                                        @endphp
                                        <label class="btn btn-primary @if ($level == 0) active @endif">
                                            <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="0" @if ($level == 0) checked @endif> Not Required
                                        </label>
                                        <label class="btn btn-primary @if ($level == 1) active @endif">
                                            <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="1" @if ($level == 1) checked @endif> Admin Only
                                        </label>
                                        <label class="btn btn-primary @if ($level == 2) active @endif">
                                            <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="2" @if ($level == 2) checked @endif> All Users
                                        </label>
                                    </div>
                                    <p class="text-muted"><small>If enabled, any account falling into the selected grouping will be required to have 2-Factor authentication enabled to use the Panel.</small></p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Allow Registration</label>
                                <div>
                                    <div class="btn-group" data-toggle="buttons">
                                        @php
                                            $registration = filter_var(old('pterodactyl:auth:registration', config('pterodactyl.auth.registration')), FILTER_VALIDATE_BOOLEAN);
                                        @endphp
                                        <label class="btn btn-primary @if (!$registration) active @endif">
                                            <input type="radio" name="pterodactyl:auth:registration" autocomplete="off" value="false" @if (!$registration) checked @endif> Disabled
                                        </label>
                                        <label class="btn btn-primary @if ($registration) active @endif">
                                            <input type="radio" name="pterodactyl:auth:registration" autocomplete="off" value="true" @if ($registration) checked @endif> Enabled
                                        </label>
                                    </div>
                                    <p class="text-muted"><small>If enabled, visitors can create their own account from the login page. Turn it off to keep the Panel invite-only.</small></p>
                                </div>
                            </div>
                        </div>
                        <hr />
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Discord Login</label>
                                <div>
                                    <div class="btn-group" data-toggle="buttons">
                                        @php
                                            $discord = filter_var(old('pterodactyl:auth:discord:enabled', config('pterodactyl.auth.discord.enabled')), FILTER_VALIDATE_BOOLEAN);
                                        @endphp
                                        <label class="btn btn-primary @if (!$discord) active @endif">
                                            <input type="radio" name="pterodactyl:auth:discord:enabled" autocomplete="off" value="false" @if (!$discord) checked @endif> Disabled
                                        </label>
                                        <label class="btn btn-primary @if ($discord) active @endif">
                                            <input type="radio" name="pterodactyl:auth:discord:enabled" autocomplete="off" value="true" @if ($discord) checked @endif> Enabled
                                        </label>
                                    </div>
                                    <p class="text-muted"><small>Lets users sign in with Discord. A user who already has an account with the same verified email address is linked automatically.</small></p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Discord Client ID</label>
                                <div>
                                    <input type="text" class="form-control" name="pterodactyl:auth:discord:client_id" value="{{ old('pterodactyl:auth:discord:client_id', config('pterodactyl.auth.discord.client_id')) }}" autocomplete="off" />
                                    <p class="text-muted"><small>The Application ID from the OAuth2 page of your app in the Discord Developer Portal.</small></p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Discord Client Secret</label>
                                <div>
                                    <input type="password" class="form-control" name="pterodactyl:auth:discord:client_secret" value="" autocomplete="new-password" placeholder="{{ \Pterodactyl\Services\Auth\AuthFeatures::discordClientSecret() ? 'Saved — leave empty to keep it' : '' }}" />
                                    <p class="text-muted"><small>The Client Secret from the same page. It is stored encrypted and never displayed again.</small></p>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label class="control-label">Discord Redirect URI</label>
                                <div>
                                    <input type="text" class="form-control" value="{{ route('auth.discord.callback') }}" readonly onclick="this.select()" />
                                    <p class="text-muted"><small>Add this exact URL in the Discord Developer Portal under OAuth2 &rarr; Redirects.</small></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
