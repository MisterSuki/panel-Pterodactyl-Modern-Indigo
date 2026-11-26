@extends('layouts.admin')

@section('title')
    Shop · Offer generator
@endsection

@section('content-header')
    <h1>Shop<small>Make your offers in one go.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.shop.offers') }}">Shop</a></li>
        <li class="active">Offer generator</li>
    </ol>
@endsection

@section('content')
@include('admin.shop._nav')
<style>
    .pd-gen-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 4px 0 0; }
    .pd-gen-nest { margin: 14px 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: rgba(235, 235, 240, 0.55); }
    .pd-gen-eggs { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 8px; }
    .pd-gen-egg { display: flex; gap: 8px; align-items: flex-start; padding: 8px 10px; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; cursor: pointer; margin: 0; font-weight: normal; }
    .pd-gen-egg:hover { border-color: rgba(255,255,255,0.2); }
    .pd-gen-egg input { margin-top: 3px; }
    .pd-gen-egg.disabled { opacity: .55; cursor: not-allowed; }
    .pd-gen-egg small { display: block; color: rgba(235, 235, 240, 0.55); }
    .pd-gen-kind { display: inline-block; margin-left: 4px; padding: 0 6px; border-radius: 10px; font-size: 10px; background: rgba(255,255,255,0.08); }
    .pd-gen-table td, .pd-gen-table th { vertical-align: middle !important; }
    .pd-gen-table tr.exists td { opacity: .5; }
</style>

<form action="{{ route('admin.shop.generator') }}" method="POST">
    {!! csrf_field() !!}
    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">The games to sell</h3></div>
                <div class="box-body">
                    <p class="pd-gen-help" style="margin-top:0">For each game, the generator makes 3 or 4 formulas (Starter, Standard, Pro, Ultra) with the memory, disk and CPU that suit it: more memory for a FiveM server than for a Discord bot. You see them before anything is made.</p>
                    @foreach ($groups as $nest => $eggs)
                        <p class="pd-gen-nest">{{ $nest }}</p>
                        <div class="pd-gen-eggs">
                            @foreach ($eggs as $egg)
                                <label class="pd-gen-egg {{ $egg['missing'] ? 'disabled' : '' }}" title="{{ $egg['missing'] ? 'Needs: ' . implode(', ', $egg['missing']) : '' }}">
                                    <input type="checkbox" name="eggs[]" value="{{ $egg['id'] }}" @checked(in_array($egg['id'], $input['eggs'] ?? [])) @disabled($egg['missing'])>
                                    <span>
                                        {{ $egg['name'] }}<span class="pd-gen-kind">{{ $egg['kind'] }}</span>
                                        @if ($egg['missing'])
                                            <small><span>To make by hand: it needs</span> {{ implode(', ', $egg['missing']) }}</small>
                                        @elseif ($egg['offers'] > 0)
                                            <small>{{ $egg['offers'] }} <span>offers already</span></small>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="box box-success">
                <div class="box-header with-border"><h3 class="box-title">Settings</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pdGenLocation">Where the servers are made</label>
                        <select id="pdGenLocation" name="location_id" class="form-control" required>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((int) ($input['location_id'] ?? 0) === $location->id)>{{ $location->short }}{{ $location->long ? ' — ' . $location->long : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Formulas per game</label>
                        <div>
                            <label style="font-weight:normal;margin-right:14px"><input type="radio" name="tiers" value="3" @checked((int) $input['tiers'] === 3)> 3 <small>(Starter, Standard, Pro)</small></label>
                            <label style="font-weight:normal"><input type="radio" name="tiers" value="4" @checked((int) $input['tiers'] === 4)> 4 <small>(+ Ultra)</small></label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-6">
                            <label for="pdGenPerGb">Price per GB of memory</label>
                            <div class="input-group">
                                <input type="text" id="pdGenPerGb" name="price_per_gb" class="form-control" value="{{ $input['price_per_gb'] }}" required>
                                <span class="input-group-addon">{{ $currency }}</span>
                            </div>
                        </div>
                        <div class="form-group col-xs-6">
                            <label for="pdGenMin">Lowest price</label>
                            <div class="input-group">
                                <input type="text" id="pdGenMin" name="minimum" class="form-control" value="{{ $input['minimum'] }}" required>
                                <span class="input-group-addon">{{ $currency }}</span>
                            </div>
                        </div>
                    </div>
                    <p class="pd-gen-help" style="margin-top:-8px">The price of a formula is its memory × the price per GB, never under the lowest price, ending in .99. You can change each price afterwards.</p>
                    <div class="form-group" style="margin-top:12px">
                        <label for="pdGenDays">Duration (days)</label>
                        <input type="number" id="pdGenDays" name="duration_days" class="form-control" min="1" max="365" value="{{ $input['duration_days'] }}" required>
                    </div>
                    <div class="checkbox checkbox-primary">
                        <input type="hidden" name="categories" value="0">
                        <input type="checkbox" id="pdGenCats" name="categories" value="1" @checked($input['categories'])>
                        <label for="pdGenCats">One category per game</label>
                    </div>
                    <div class="checkbox checkbox-primary no-margin-bottom">
                        <input type="hidden" name="enable" value="0">
                        <input type="checkbox" id="pdGenEnable" name="enable" value="1" @checked($input['enable'])>
                        <label for="pdGenEnable">Put them on sale at once</label>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" name="confirm" value="0" class="btn btn-primary btn-sm"><i class="fa fa-eye"></i> <span>See the offers</span></button>
                </div>
            </div>
        </div>
    </div>

    @if ($preview !== null)
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">The offers that will be made</h3>
                <div class="box-tools">
                    <button type="submit" name="confirm" value="1" class="btn btn-success btn-sm" @disabled(collect($preview)->where('exists', false)->isEmpty())>
                        <i class="fa fa-magic"></i> <span>Make these offers</span> ({{ collect($preview)->where('exists', false)->count() }})
                    </button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                @if (count($preview) === 0)
                    <p class="text-center" style="padding:20px"><span>Choose at least one game (the ones that need values must be made by hand).</span></p>
                @else
                    <table class="table table-hover pd-gen-table">
                        <thead>
                            <tr>
                                <th>Offer</th>
                                <th>Category</th>
                                <th>Memory</th>
                                <th>Disk</th>
                                <th>CPU</th>
                                <th>Databases</th>
                                <th>Backups</th>
                                <th>Ports</th>
                                <th class="text-right">Price</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview as $row)
                                <tr class="{{ $row['exists'] ? 'exists' : '' }}">
                                    <td>{{ $row['offer'] }} @if ($row['featured'])<span class="label label-primary"><span>Popular</span></span>@endif</td>
                                    <td>{{ $row['category'] }}</td>
                                    <td>{{ rtrim(rtrim(number_format($row['memory'] / 1024, 1, '.', ''), '0'), '.') }} GB</td>
                                    <td>{{ rtrim(rtrim(number_format($row['disk'] / 1024, 1, '.', ''), '0'), '.') }} GB</td>
                                    <td>{{ $row['cpu'] }}%</td>
                                    <td>{{ $row['databases'] }}</td>
                                    <td>{{ $row['backups'] }}</td>
                                    <td>{{ $row['ports'] }}</td>
                                    <td class="text-right"><strong>{{ number_format($row['price_cents'] / 100, 2, ',', ' ') }} {{ $currency }}</strong></td>
                                    <td>@if ($row['exists'])<span class="label label-default"><span>Already exists</span></span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endif
</form>
@endsection
