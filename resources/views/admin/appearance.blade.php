@extends('layouts.admin')

@section('title')
    Appearance
@endsection

@section('content-header')
    <h1>Appearance<small>The background of the panel and its accent colour.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Appearance</li>
    </ol>
@endsection

@section('content')
@php
    $accents = ['blue' => ['Blue', '#0a84ff'], 'indigo' => ['Indigo', '#5e5ce6'], 'purple' => ['Purple', '#bf5af2'], 'pink' => ['Pink', '#ff375f'], 'red' => ['Red', '#ff453a'], 'orange' => ['Orange', '#ff9f0a'], 'green' => ['Green', '#30d158'], 'teal' => ['Teal', '#30b0c7'], 'graphite' => ['Graphite', '#8e8e93']];
    $effects = ['aurora' => 'Aurora', 'silk' => 'Silk', 'plasma' => 'Plasma', 'waves' => 'Waves', 'lines' => 'Lines', 'glow' => 'Glow', 'beams' => 'Beams', 'stars' => 'Stars', 'bubbles' => 'Bubbles'];
    $gradients = ['liquid' => 'Liquid Glass', 'midnight' => 'Midnight', 'ocean' => 'Ocean', 'sunset' => 'Sunset', 'forest' => 'Forest'];
    $mode = old('mode', $site['mode']);
@endphp
<style>
    .pd-ap-section { margin-bottom: 4px; }
    .pd-ap-help { color: rgba(235, 235, 240, 0.6); font-size: 12px; margin: 6px 0 0; }
    .pd-ap-swatches { display: flex; flex-wrap: wrap; gap: 14px; }
    .pd-ap-swatch { display: flex; flex-direction: column; align-items: center; gap: 6px; width: 64px; cursor: pointer; font-weight: 400; margin: 0; }
    .pd-ap-swatch input, .pd-ap-card input, .pd-ap-seg input { position: absolute; opacity: 0; pointer-events: none; }
    .pd-ap-dot { width: 42px; height: 42px; border-radius: 50%; border: 2px solid transparent; transition: transform .15s ease; display: flex; align-items: center; justify-content: center; color: #fff; }
    .pd-ap-swatch:hover .pd-ap-dot { transform: scale(1.1); }
    .pd-ap-swatch input:checked + .pd-ap-dot { border-color: #fff; }
    .pd-ap-swatch input:checked + .pd-ap-dot::after { content: '\f00c'; font-family: FontAwesome; font-size: 13px; }
    .pd-ap-swatch small { font-size: 12px; color: rgba(235, 235, 240, 0.75); }
    .pd-ap-seg { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px; border-radius: 18px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 16px; }
    .pd-ap-seg label { flex: 1; position: relative; margin: 0; text-align: center; padding: 9px 12px; border-radius: 14px; cursor: pointer; font-weight: 500; color: rgba(235, 235, 240, 0.75); transition: background .15s ease, color .15s ease; white-space: nowrap; }
    .pd-ap-seg label:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
    .pd-ap-seg label.is-active { background: rgba(255, 255, 255, 0.2); color: #fff; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3); }
    .pd-ap-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; }
    .pd-ap-card { position: relative; display: block; margin: 0; border-radius: 16px; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.12); cursor: pointer; transition: transform .15s ease, border-color .15s ease; font-weight: 600; }
    .pd-ap-card:hover { transform: translateY(-2px); border-color: rgba(255, 255, 255, 0.3); }
    .pd-ap-card .pd-ap-pic { display: block; height: 104px; background: #0b0d18 center / cover no-repeat; }
    .pd-ap-card .pd-ap-name { position: absolute; left: 0; right: 0; bottom: 0; padding: 22px 12px 8px; color: #fff; font-size: 13px; background: linear-gradient(180deg, transparent, rgba(0, 0, 0, 0.7)); }
    .pd-ap-card input:checked ~ .pd-ap-pic { box-shadow: inset 0 0 0 3px rgb(var(--pd-accent-500)); }
    .pd-ap-card input:checked ~ .pd-ap-name::after { content: '\f00c'; font-family: FontAwesome; float: right; width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 50%; background: rgb(var(--pd-accent-500)); font-size: 11px; }
    .pd-ap-pane { display: none; }
    .pd-ap-pane.is-active { display: block; }
    .pd-ap-image { display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
    .pd-ap-image .pd-ap-pic { width: 260px; max-width: 100%; height: 146px; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.12); background: #0b0d18 center / cover no-repeat; display: flex; align-items: center; justify-content: center; color: rgba(235, 235, 240, 0.5); }
    .pd-ap-range { display: flex; align-items: center; gap: 12px; }
    .pd-ap-range input { flex: 1; accent-color: rgb(var(--pd-accent-500)); }
    .pd-ap-range output { min-width: 48px; text-align: right; font-variant-numeric: tabular-nums; color: rgba(235, 235, 240, 0.85); }
</style>

<form action="{{ route('admin.appearance') }}" method="POST" enctype="multipart/form-data" autocomplete="off" id="pdAppearanceForm">
    {!! csrf_field() !!}
    <div class="row">
        <div class="col-lg-8">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Background</h3></div>
                <div class="box-body">
                    <div class="pd-ap-seg" data-pd-seg>
                        @foreach (['gradient' => 'Gradient', 'animated' => 'Animated', 'image' => 'Picture'] as $value => $label)
                            <label class="{{ $mode === $value ? 'is-active' : '' }}"><input type="radio" name="mode" value="{{ $value }}" @checked($mode === $value)><span>{{ $label }}</span></label>
                        @endforeach
                    </div>

                    <div class="pd-ap-pane {{ $mode === 'gradient' ? 'is-active' : '' }}" data-pane="gradient">
                        <div class="pd-ap-grid">
                            @foreach ($gradients as $value => $label)
                                <label class="pd-ap-card">
                                    <input type="radio" name="gradient" value="{{ $value }}" @checked(old('gradient', $site['gradient']) === $value)>
                                    <span class="pd-ap-pic" data-gradient="{{ $value }}"></span>
                                    <span class="pd-ap-name">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="pd-ap-pane {{ $mode === 'animated' ? 'is-active' : '' }}" data-pane="animated">
                        <div class="pd-ap-grid">
                            @foreach ($effects as $value => $label)
                                <label class="pd-ap-card">
                                    <input type="radio" name="effect" value="{{ $value }}" @checked(old('effect', $site['effect']) === $value)>
                                    <span class="pd-ap-pic" data-effect="{{ $value }}"></span>
                                    <span class="pd-ap-name">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="pd-ap-help"><span>Drawn by the graphics card at half the size of the screen and at most 30 frames a second. It stops while the tab is hidden, and stays still for people who turned animations off.</span></p>
                    </div>

                    <div class="pd-ap-pane {{ $mode === 'image' ? 'is-active' : '' }}" data-pane="image">
                        <div class="pd-ap-image">
                            <div class="pd-ap-pic" id="pdApImagePreview" @if ($imageUrl) style="background-image: url('{{ $imageUrl }}')" @endif>
                                @if (!$imageUrl)<span>No picture yet</span>@endif
                            </div>
                            <div style="flex: 1; min-width: 220px;">
                                <div class="form-group">
                                    <label for="pdApImage">Picture</label>
                                    <input type="file" id="pdApImage" name="image" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
                                    <p class="pd-ap-help"><span>png, jpeg, webp or gif, 8 MB at most. A wide picture (1920 × 1080 or more) looks best.</span></p>
                                </div>
                                @if ($hasImage)
                                    <button type="submit" class="btn btn-sm btn-danger" formaction="{{ route('admin.appearance.remove-image') }}" formnovalidate><i class="fa fa-trash"></i> <span>Remove the picture</span></button>
                                @endif
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 16px;">
                            <label for="pdApBlur">Blur of the picture</label>
                            <div class="pd-ap-range"><input type="range" id="pdApBlur" name="blur" min="0" max="30" step="1" value="{{ old('blur', $site['blur']) }}"><output data-for="pdApBlur">{{ old('blur', $site['blur']) }} px</output></div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 16px;">
                        <label for="pdApDim">Darken the background</label>
                        <div class="pd-ap-range"><input type="range" id="pdApDim" name="dim" min="0" max="80" step="5" value="{{ old('dim', $site['dim']) }}"><output data-for="pdApDim">{{ old('dim', $site['dim']) }} %</output></div>
                        <p class="pd-ap-help"><span>Makes the text easier to read over a bright picture or effect.</span></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Accent colour</h3></div>
                <div class="box-body">
                    <div class="pd-ap-swatches">
                        @foreach ($accents as $value => [$label, $hex])
                            <label class="pd-ap-swatch">
                                <input type="radio" name="accent" value="{{ $value }}" @checked(old('accent', $site['accent']) === $value)>
                                <span class="pd-ap-dot" style="background: {{ $hex }}; box-shadow: 0 6px 18px -6px {{ $hex }};"></span>
                                <small>{{ $label }}</small>
                            </label>
                        @endforeach
                    </div>
                    <p class="pd-ap-help"><span>The colour of everybody who did not pick their own.</span></p>
                </div>
            </div>
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">The users</h3></div>
                <div class="box-body">
                    <label class="pd-switch"><input type="checkbox" name="user_choice" value="1" @checked(old('user_choice', $site['user_choice']))><i class="pd-switch-track"></i><span>Let users pick their own background</span></label>
                    <p class="pd-ap-help"><span>Each user can always pick their accent colour, in Account → Appearance. When this is off, everybody gets the background chosen here.</span></p>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary pull-right"><i class="fa fa-save"></i> <span>Save</span></button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var form = document.getElementById('pdAppearanceForm');
            var api = window.PdAppearance;
            var objectUrl = null;

            function value(name) {
                var checked = form.querySelector('input[name="' + name + '"]:checked');

                return checked ? checked.value : null;
            }

            function current() {
                return {
                    accent: value('accent') || 'blue',
                    mode: value('mode') || 'gradient',
                    gradient: value('gradient') || 'liquid',
                    effect: value('effect') || 'aurora',
                    dim: Number(form.querySelector('[name="dim"]').value),
                    blur: Number(form.querySelector('[name="blur"]').value),
                    userChoice: true,
                    image: objectUrl || (api && api.site.image) || null,
                };
            }

            function paintCards() {
                if (!api) return;
                var accent = value('accent') || 'blue';
                form.querySelectorAll('[data-effect]').forEach(function (el) {
                    var url = api.thumbnail(el.getAttribute('data-effect'), accent, 320, 180);
                    if (url) el.style.backgroundImage = 'url(' + url + ')';
                });
                form.querySelectorAll('[data-gradient]').forEach(function (el) {
                    var name = el.getAttribute('data-gradient');
                    el.style.background = api.gradientCss(name) || 'radial-gradient(55% 45% at 12% 8%, rgb(var(--pd-accent-500) / 0.55), transparent 70%), radial-gradient(45% 40% at 88% 12%, rgba(191, 90, 242, 0.45), transparent 70%), radial-gradient(50% 45% at 82% 92%, rgba(255, 55, 95, 0.38), transparent 70%), linear-gradient(160deg, #0b1030 0%, #160c2c 48%, #05060f 100%)';
                });
            }

            // Shows the choice on this page right away; nothing is kept until "Save".
            function preview() {
                var site = current();
                form.querySelectorAll('[data-pd-seg] label').forEach(function (label) {
                    label.classList.toggle('is-active', label.querySelector('input').checked);
                });
                form.querySelectorAll('[data-pane]').forEach(function (pane) {
                    pane.classList.toggle('is-active', pane.getAttribute('data-pane') === site.mode);
                });
                form.querySelectorAll('output[data-for]').forEach(function (out) {
                    var input = document.getElementById(out.getAttribute('data-for'));
                    out.textContent = input.value + (input.name === 'dim' ? ' %' : ' px');
                });
                if (api) api.preview({ accent: 'default', mode: 'default' }, site);
            }

            form.addEventListener('change', function (event) {
                if (event.target.name === 'accent') paintCards();
                preview();
            });
            form.addEventListener('input', function (event) {
                if (event.target.type === 'range') preview();
            });
            document.getElementById('pdApImage').addEventListener('change', function (event) {
                var file = event.target.files && event.target.files[0];
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                objectUrl = file ? URL.createObjectURL(file) : null;
                var box = document.getElementById('pdApImagePreview');
                if (objectUrl) {
                    box.style.backgroundImage = 'url(' + objectUrl + ')';
                    box.textContent = '';
                    var radio = form.querySelector('input[name="mode"][value="image"]');
                    radio.checked = true;
                }
                preview();
            });

            paintCards();
        })();
    </script>
@endsection
