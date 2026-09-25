@extends('layouts.admin')

@section('title')
    Server — {{ $server->name }}: Manage
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>Additional actions to control this server.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.servers') }}">Servers</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">Manage</li>
    </ol>
@endsection

@section('content')
    @include('admin.servers.partials.navigation')
    <div class="row equal-height">
        <div class="col-sm-4">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">Reinstall Server</h3>
                </div>
                <div class="box-body">
                    <p>This will reinstall the server with the assigned service scripts. <strong>Danger!</strong> This could overwrite server data.</p>
                </div>
                <div class="box-footer">
                    @if(! $server->canBeReinstalled())
                        <button class="btn btn-danger disabled">Reinstall Server</button>
                        <p style="padding-top: 1rem;">This server is set to skip its install script. Disable "Skip Egg Install Script" on the startup page to reinstall it.</p>
                    @elseif($server->isInstalled())
                        <form action="{{ route('admin.servers.view.manage.reinstall', $server->id) }}" method="POST">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-danger">Reinstall Server</button>
                        </form>
                    @else
                        <button class="btn btn-danger disabled">Server Must Install Properly to Reinstall</button>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Install Status</h3>
                </div>
                <div class="box-body">
                    <p>If you need to change the install status from uninstalled to installed, or vice versa, you may do so with the button below.</p>
                </div>
                <div class="box-footer">
                    <form action="{{ route('admin.servers.view.manage.toggle', $server->id) }}" method="POST">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-primary">Toggle Install Status</button>
                    </form>
                </div>
            </div>
        </div>

        @if(! $server->isSuspended())
            <div class="col-sm-4">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Suspend Server</h3>
                    </div>
                    <form action="{{ route('admin.servers.view.manage.suspension', $server->id) }}" method="POST">
                        <div class="box-body">
                            <p>This will suspend the server, stop any running processes, and immediately block the user from being able to access their files or otherwise manage the server through the panel or API.</p>
                            <div class="form-group">
                                <label class="control-label" for="suspendReason">Reason <span class="text-muted small">shown to the user, optional</span></label>
                                <textarea id="suspendReason" name="reason" class="form-control" rows="3" maxlength="500" placeholder="For example: payment overdue, abuse report, maintenance...">{{ old('reason') }}</textarea>
                            </div>
                            <div class="form-group">
                                <label class="control-label" for="suspendDuration">For how long</label>
                                <select id="suspendDuration" name="duration" class="form-control" data-suspension-duration>
                                    <option value="forever">Until I lift it</option>
                                    <option value="1h">1 hour from now</option>
                                    <option value="6h">6 hours from now</option>
                                    <option value="24h">24 hours from now</option>
                                    <option value="3d">3 days from now</option>
                                    <option value="7d">7 days from now</option>
                                    <option value="30d">30 days from now</option>
                                    <option value="custom">Until a date I choose...</option>
                                </select>
                            </div>
                            <div class="form-group" data-suspension-custom style="display: none;">
                                <label class="control-label" for="suspendUntil">End date and time</label>
                                <input type="datetime-local" id="suspendUntil" name="until" class="form-control" value="{{ old('until') }}" />
                                <p class="text-muted small">In the panel's timezone ({{ config('app.timezone') }}). The server gets its access back by itself, checked every minute.</p>
                            </div>
                        </div>
                        <div class="box-footer">
                            {!! csrf_field() !!}
                            <input type="hidden" name="action" value="suspend" />
                            <button type="submit" class="btn btn-warning @if(! is_null($server->transfer)) disabled @endif">Suspend Server</button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            @php($suspension = \Pterodactyl\Models\ServerSuspension::forServer($server))
            <div class="col-sm-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Suspension</h3>
                    </div>
                    <form action="{{ route('admin.servers.view.manage.suspension', $server->id) }}" method="POST">
                        <div class="box-body">
                            <table class="table table-condensed" style="margin-bottom: 10px;">
                                <tr>
                                    <td class="text-muted" style="width: 30%;">Since</td>
                                    <td>{{ $suspension?->created_at ? $suspension->created_at->format('M j, Y H:i') : 'Unknown' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">By</td>
                                    <td>{{ $suspension?->admin?->username ?? 'Unknown' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Until</td>
                                    <td>
                                        @if($suspension?->suspended_until)
                                            {{ $suspension->suspended_until->format('M j, Y H:i') }} <span class="text-muted">({{ $suspension->suspended_until->diffForHumans() }})</span>
                                        @else
                                            Until an administrator lifts it
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            <div class="form-group">
                                <label class="control-label" for="suspendReason">Reason <span class="text-muted small">shown to the user</span></label>
                                <textarea id="suspendReason" name="reason" class="form-control" rows="3" maxlength="500" placeholder="No reason given">{{ old('reason', $suspension?->reason) }}</textarea>
                            </div>
                            <div class="form-group">
                                <label class="control-label" for="suspendDuration">End of the suspension</label>
                                <select id="suspendDuration" name="duration" class="form-control" data-suspension-duration>
                                    <option value="keep">Keep the end date as it is</option>
                                    <option value="forever">Until I lift it</option>
                                    <option value="1h">1 hour from now</option>
                                    <option value="6h">6 hours from now</option>
                                    <option value="24h">24 hours from now</option>
                                    <option value="3d">3 days from now</option>
                                    <option value="7d">7 days from now</option>
                                    <option value="30d">30 days from now</option>
                                    <option value="custom">Until a date I choose...</option>
                                </select>
                            </div>
                            <div class="form-group" data-suspension-custom style="display: none;">
                                <label class="control-label" for="suspendUntil">End date and time</label>
                                <input type="datetime-local" id="suspendUntil" name="until" class="form-control" value="{{ old('until') }}" />
                                <p class="text-muted small">In the panel's timezone ({{ config('app.timezone') }}).</p>
                            </div>
                        </div>
                        <div class="box-footer">
                            {!! csrf_field() !!}
                            <button type="submit" name="action" value="update" class="btn btn-default">Save changes</button>
                            <button type="submit" name="action" value="unsuspend" class="btn btn-success pull-right">Unsuspend Server</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if(is_null($server->transfer))
            <div class="col-sm-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Transfer Server</h3>
                    </div>
                    <div class="box-body">
                        <p>
                            Transfer this server to another node connected to this panel.
                            <strong>Warning!</strong> This feature has not been fully tested and may have bugs.
                        </p>
                    </div>

                    <div class="box-footer">
                        @if($canTransfer)
                            <button class="btn btn-success" data-toggle="modal" data-target="#transferServerModal">Transfer Server</button>
                        @else
                            <button class="btn btn-success disabled">Transfer Server</button>
                            <p style="padding-top: 1rem;">Transferring a server requires more than one node to be configured on your panel.</p>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="col-sm-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Transfer Server</h3>
                    </div>
                    <div class="box-body">
                        <p>
                            This server is currently being transferred to another node.
                            Transfer was initiated at <strong>{{ $server->transfer->created_at }}</strong>
                        </p>
                    </div>

                    <div class="box-footer">
                        <button class="btn btn-success disabled">Transfer Server</button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="modal fade" id="transferServerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="{{ route('admin.servers.view.manage.transfer', $server->id) }}" method="POST">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">Transfer Server</h4>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label for="pNodeId">Node</label>
                                <select name="node_id" id="pNodeId" class="form-control">
                                    @foreach($locations as $location)
                                        <optgroup label="{{ $location->long }} ({{ $location->short }})">
                                            @foreach($location->nodes as $node)

                                                @if($node->id != $server->node_id)
                                                    <option value="{{ $node->id }}"
                                                            @if($location->id === old('location_id')) selected @endif
                                                    >{{ $node->name }}</option>
                                                @endif

                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <p class="small text-muted no-margin">The node which this server will be transferred to.</p>
                            </div>

                            <div class="form-group col-md-12">
                                <label for="pAllocation">Default Allocation</label>
                                <select name="allocation_id" id="pAllocation" class="form-control"></select>
                                <p class="small text-muted no-margin">The main allocation that will be assigned to this server.</p>
                            </div>

                            <div class="form-group col-md-12">
                                <label for="pAllocationAdditional">Additional Allocation(s)</label>
                                <select name="allocation_additional[]" id="pAllocationAdditional" class="form-control" multiple></select>
                                <p class="small text-muted no-margin">Additional allocations to assign to this server on creation.</p>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        {!! csrf_field() !!}
                        <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    {!! Theme::js('vendor/lodash/lodash.js') !!}

    @if($canTransfer)
        {!! Theme::js('js/admin/server/transfer.js') !!}
    @endif

    <script>
        // The date field only shows when "Until a date I choose" is picked.
        $('[data-suspension-duration]').on('change', function () {
            $('[data-suspension-custom]').toggle($(this).val() === 'custom');
        }).trigger('change');
    </script>
@endsection
