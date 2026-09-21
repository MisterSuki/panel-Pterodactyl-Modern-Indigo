{{-- Shared fields of the role forms. Expects $catalog, $selected and optionally $role. --}}
<div class="box-body">
    <div class="row">
        <div class="form-group col-md-6">
            <label for="role-name" class="control-label">Role Name</label>
            <div>
                <input type="text" id="role-name" name="name" class="form-control" value="{{ old('name', $role->name ?? '') }}" placeholder="Moderator" maxlength="64" autocomplete="off" />
                <p class="text-muted"><small>Shown in the list of roles and next to the people who have it.</small></p>
            </div>
        </div>
        <div class="form-group col-md-6">
            <label for="role-description" class="control-label">Description</label>
            <div>
                <input type="text" id="role-description" name="description" class="form-control" value="{{ old('description', $role->description ?? '') }}" placeholder="Handles support tickets and suspends abusive servers" maxlength="191" autocomplete="off" />
                <p class="text-muted"><small>Optional. A reminder of what this role is for.</small></p>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <label class="control-label">Permissions</label>
            <p class="text-muted">
                <small>
                    <strong>View</strong> lets people open the pages of a section. <strong>Manage</strong> lets them change things and always includes view.
                    <a href="#" data-select-all="all">Select all</a> &middot; <a href="#" data-select-all="none">Clear all</a>
                </small>
            </p>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th class="text-center" style="width:80px;">View</th>
                            <th class="text-center" style="width:80px;">Manage</th>
                            <th>What it allows</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($catalog as $section => $info)
                            <tr class="align-middle">
                                <td><strong>{{ $info['label'] }}</strong></td>
                                @foreach(['view', 'manage'] as $ability)
                                    <td class="text-center">
                                        @if(isset($info['abilities'][$ability]))
                                            <input type="checkbox" name="permissions[]" value="{{ $section }}.{{ $ability }}" data-section="{{ $section }}" data-ability="{{ $ability }}" @if(in_array($section . '.' . $ability, $selected, true)) checked @endif />
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    @foreach($info['abilities'] as $ability => $text)
                                        <div><small class="text-muted"><strong>{{ ucfirst($ability) }}:</strong> {{ $text }}</small></div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-muted no-margin">
                <small>The Application API and the roles themselves can never be handed out: both could be used to gain more access, so only administrators have them.</small>
            </p>
        </div>
    </div>
</div>
