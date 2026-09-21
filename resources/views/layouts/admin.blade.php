<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'Pterodactyl') }} - @yield('title')</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">

        <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
        <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
        <link rel="manifest" href="/favicons/manifest.json">
        <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
        <link rel="shortcut icon" href="/favicons/favicon.ico">
        <meta name="msapplication-config" content="/favicons/browserconfig.xml">
        <meta name="theme-color" content="#0e4688">

        @include('layouts.scripts')

        <script>window.PterodactylUiLocale = @json(Auth::user()->language ?? \Pterodactyl\Services\Helpers\Locales::default());</script>
        <script>window.PterodactylDictionaryVersion = @json(\Pterodactyl\Services\Helpers\Locales::dictionaryVersion());</script>
        <script src="/js/translator.js?v={{ @filemtime(public_path('js/translator.js')) }}"></script>

        @section('scripts')
            {!! Theme::css('vendor/select2/select2.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/admin.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/colors/skin-blue.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/sweetalert/sweetalert.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/animate/animate.min.css?t={cache-version}') !!}
            {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">

            <!--[if lt IE 9]>
            <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
            <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
            <![endif]-->
        @show
    </head>
    <body class="hold-transition skin-blue fixed sidebar-mini">
        <div class="wrapper">
            <header class="main-header">
                <a href="{{ route('index') }}" class="logo">
                    <span>{{ config('app.name', 'Pterodactyl') }}</span>
                </a>
                <nav class="navbar navbar-static-top">
                    <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </a>
                    <div class="navbar-custom-menu">
                        <ul class="nav navbar-nav">
                            <li class="user-menu">
                                <a href="{{ route('account') }}">
                                    <img src="{{ Auth::user()->avatarUrl() }}" class="user-image" alt="User Image">
                                    <span class="hidden-xs">{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</span>
                                </a>
                            </li>
                            <li><a href="{{ route('index') }}" data-toggle="tooltip" data-placement="bottom" title="Exit Admin Control"><i class="fa fa-server"></i></a></li>
                            <li><a href="{{ route('auth.logout') }}" id="logoutButton" data-toggle="tooltip" data-placement="bottom" title="Logout"><i class="fa fa-sign-out"></i></a></li>
                        </ul>
                    </div>
                </nav>
            </header>
            <aside class="main-sidebar">
                <section class="sidebar">
                    @php
                        $adminUser = Auth::user();
                        $can = fn (string $section): bool => $adminUser->canAccessAdminSection($section);
                        $isRoot = (bool) $adminUser->root_admin;
                        $current = (string) Route::currentRouteName();
                        $counts = app(\Pterodactyl\Services\Admin\OverviewService::class)->menuCounts();
                        // [label, route, icon, the start of the route names that make it the current page, visible, count]
                        $groups = [
                            'BASIC ADMINISTRATION' => [
                                ['Overview', 'admin.index', 'fa-home', '=admin.index', true, null],
                                ['Settings', 'admin.settings', 'fa-wrench', 'admin.settings', $can('settings'), null],
                                ['Home page', 'admin.home-page', 'fa-columns', 'admin.home-page', $can('settings'), null],
                                ['Application API', 'admin.api.index', 'fa-gamepad', 'admin.api', $isRoot, null],
                            ],
                            'MANAGEMENT' => [
                                ['Databases', 'admin.databases', 'fa-database', 'admin.databases', $can('databases'), null],
                                ['Locations', 'admin.locations', 'fa-globe', 'admin.locations', $can('locations'), null],
                                ['Nodes', 'admin.nodes', 'fa-sitemap', 'admin.nodes', $can('nodes'), 'nodes'],
                                ['Servers', 'admin.servers', 'fa-server', 'admin.servers', $can('servers'), 'servers'],
                                ['Users', 'admin.users', 'fa-users', 'admin.users', $can('users'), 'users'],
                                ['Staff Roles', 'admin.roles', 'fa-id-badge', 'admin.roles', $isRoot, null],
                            ],
                            'SUPPORT' => [
                                ['Tickets', 'admin.tickets', 'fa-life-ring', 'admin.tickets', $can('tickets'), 'tickets'],
                            ],
                            'SHOP' => [
                                ['Shop', 'admin.shop.offers', 'fa-shopping-cart', 'admin.shop', $can('shop'), null],
                            ],
                            'SERVICE MANAGEMENT' => [
                                ['Mounts', 'admin.mounts', 'fa-magic', 'admin.mounts', $can('mounts'), null],
                                ['Nests', 'admin.nests', 'fa-th-large', 'admin.nests', $can('nests'), null],
                            ],
                        ];
                    @endphp
                    <div class="pd-user">
                        <img src="{{ $adminUser->avatarUrl() }}" alt="">
                        <div>
                            <strong>{{ trim($adminUser->name_first . ' ' . $adminUser->name_last) ?: $adminUser->username }}</strong>
                            <small>@if ($isRoot)<span>Administrator</span>@else{{ $adminUser->adminRole?->name ?? 'Staff' }}@endif</small>
                        </div>
                    </div>
                    <ul class="sidebar-menu">
                        @foreach ($groups as $heading => $items)
                            @php($visible = array_filter($items, fn ($item) => $item[4]))
                            @if (count($visible) > 0)
                                <li class="header">{{ $heading }}</li>
                                @foreach ($visible as [$label, $route, $icon, $match, , $countKey])
                                    @php($active = str_starts_with($match, '=') ? $current === substr($match, 1) : str_starts_with($current, $match))
                                    <li class="{{ $active ? 'active' : '' }}">
                                        <a href="{{ route($route) }}" title="{{ $label }}">
                                            <i class="fa {{ $icon }}"></i> <span>{{ $label }}</span>
                                            @if ($countKey && isset($counts[$countKey]))
                                                <small class="pd-count">{{ $counts[$countKey] }}</small>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            @endif
                        @endforeach
                    </ul>
                    <div class="pd-sidebar-foot">
                        <a href="{{ route('index') }}"><i class="fa fa-arrow-left"></i> <span>Back to the dashboard</span></a>
                    </div>
                </section>
            </aside>
            <div class="content-wrapper">
                <section class="content-header">
                    @yield('content-header')
                </section>
                <section class="content">
                    <div class="row">
                        <div class="col-xs-12">
                            @if (count($errors) > 0)
                                <div class="alert alert-danger">
                                    There was an error validating the data provided.<br><br>
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @foreach (Alert::getMessages() as $type => $messages)
                                @foreach ($messages as $message)
                                    <div class="alert alert-{{ $type }} alert-dismissable" role="alert">
                                        {{ $message }}
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                    @yield('content')
                </section>
            </div>
            <footer class="main-footer">
                <div class="pull-right small text-gray" style="margin-right:10px;margin-top:-7px;">
                    <strong><i class="fa fa-fw {{ $appIsGit ? 'fa-git-square' : 'fa-code-fork' }}"></i></strong> {{ $appVersion }}<br />
                    <strong><i class="fa fa-fw fa-clock-o"></i></strong> {{ round(microtime(true) - LARAVEL_START, 3) }}s
                </div>
                @php($copyright = \Pterodactyl\Services\Helpers\Copyright::custom())
                @if ($copyright)
                    @if ($copyright['url'])<a href="{{ $copyright['url'] }}" rel="noopener nofollow noreferrer" target="_blank">{{ $copyright['text'] }}</a>@else{{ $copyright['text'] }}@endif
                @else
                    Copyright &copy; 2015 - {{ date('Y') }} <a href="https://pterodactyl.io/">Pterodactyl Software</a>.
                @endif
            </footer>
        </div>
        @section('footer-scripts')
            <script src="/js/keyboard.polyfill.js" type="application/javascript"></script>
            <script>keyboardeventKeyPolyfill.polyfill();</script>

            {!! Theme::js('vendor/jquery/jquery.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/sweetalert/sweetalert.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap/bootstrap.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/slimscroll/jquery.slimscroll.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/adminlte/app.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap-notify/bootstrap-notify.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/select2/select2.full.min.js?t={cache-version}') !!}
            {!! Theme::js('js/admin/functions.js?t={cache-version}') !!}
            <script src="/js/autocomplete.js" type="application/javascript"></script>

            @if(Auth::user()->isStaff())
                <script>
                    $('#logoutButton').on('click', function (event) {
                        event.preventDefault();

                        var that = this;
                        swal({
                            title: 'Do you want to log out?',
                            type: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d9534f',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Log out'
                        }, function () {
                             $.ajax({
                                type: 'POST',
                                url: '{{ route('auth.logout') }}',
                                data: {
                                    _token: '{{ csrf_token() }}'
                                },complete: function () {
                                    window.location.href = '{{route('auth.login')}}';
                                }
                        });
                    });
                });
                </script>
            @endif

            <script>
                $(function () {
                    $('[data-toggle="tooltip"]').tooltip();
                })
            </script>

            <script>
                // Memory and disk are kept in MiB by the panel, but people think in GB. A field marked data-gb is shown
                // in GB (1 GB = 1024 MiB) and still sends MiB. The real value is only rewritten when the field is edited,
                // so a server with 1500 MiB is not changed just by saving the page.
                $(function () {
                    $('input[data-gb]').each(function () {
                        var $shown = $(this);
                        var name = $shown.attr('name');
                        var id = $shown.attr('id');
                        var mib = $.trim($shown.val());
                        var $hidden = $('<input type="hidden">').attr('name', name).val(mib);
                        var $group = $shown.closest('.input-group');
                        var $hint = $('<p class="text-muted small" style="margin: 4px 0 0;"></p>');

                        $shown.removeAttr('name').removeAttr('data-gb');
                        if (id) {
                            $hidden.attr('id', id);
                            $shown.attr('id', id + 'Gb');
                            $('label[for="' + id + '"]').attr('for', id + 'Gb');
                        }
                        ($group.length ? $group : $shown).before($hidden);
                        ($group.length ? $group : $shown).after($hint);
                        $group.find('.input-group-addon').text('GB').attr('title', '1 GB = 1024 MiB');

                        function show() {
                            var value = $hidden.val();
                            $hint.text(/^\d+$/.test(value) && parseInt(value, 10) > 0 ? '= ' + parseInt(value, 10).toLocaleString('en-US') + ' MiB' : (value === '0' ? 'Unlimited' : ''));
                        }

                        if (/^-?\d+(\.\d+)?$/.test(mib)) {
                            $shown.val(String(parseFloat((parseFloat(mib) / 1024).toFixed(3))));
                        }
                        show();

                        $shown.on('input change', function () {
                            var raw = $.trim($shown.val()).replace(',', '.');
                            if (raw === '') {
                                $hidden.val('');
                            } else if (/^\d+(\.\d+)?$/.test(raw)) {
                                $hidden.val(String(Math.round(parseFloat(raw) * 1024)));
                            } else {
                                // Not a number: sent as typed, so the panel's own validation reports it.
                                $hidden.val(raw);
                            }
                            show();
                        });
                    });
                });
            </script>
        @show
    </body>
</html>
