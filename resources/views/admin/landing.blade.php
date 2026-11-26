@extends('layouts.admin')

@section('title')
    Home page
@endsection

@section('content-header')
    <h1>Home page<small>What visitors see before they sign in.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Home page</li>
    </ol>
@endsection

@section('content')
@php $v = fn (string $key) => old($key, $content[$key] ?? ''); @endphp
<form action="{{ route('admin.home-page') }}" method="POST" autocomplete="off">
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">The page</h3>
                    <div class="box-tools"><a href="{{ route('landing') }}" target="_blank" rel="noopener" class="btn btn-xs btn-default"><i class="fa fa-external-link"></i> <span>See the page</span></a></div>
                </div>
                <div class="box-body">
                    <label class="pd-switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled))><i class="pd-switch-track"></i><span>Show this page to visitors</span></label>
                    <p class="text-muted small"><span>When it is off, visitors are sent straight to the sign-in page, as before. People who are signed in always get their dashboard.</span></p>
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" class="form-control" maxlength="120" value="{{ $v('title') }}">
                    </div>
                    <div class="form-group">
                        <label for="subtitle">Subtitle</label>
                        <textarea id="subtitle" name="subtitle" class="form-control" rows="3" maxlength="300">{{ $v('subtitle') }}</textarea>
                    </div>
                    <p class="text-muted small"><i class="fa fa-lock"></i> <span>The buttons to log in and to create an account are fixed: they cannot be changed, so the way in never breaks. The registration button is hidden while registration is closed.</span></p>
                    <label class="pd-switch"><input type="checkbox" name="show_stats" value="1" @checked(old('show_stats', $content['show_stats']))><i class="pd-switch-track"></i><span>Show the number of servers hosted</span></label>
                    <p class="text-muted small"><span>Only the number is shown.</span></p>
                </div>
            </div>
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">About and offers</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="about_title">Title of the presentation</label>
                        <input type="text" id="about_title" name="about_title" class="form-control" maxlength="80" value="{{ $v('about_title') }}">
                    </div>
                    <div class="form-group">
                        <label for="about_text">Presentation</label>
                        <textarea id="about_text" name="about_text" class="form-control" rows="5" maxlength="2000">{{ $v('about_text') }}</textarea>
                        <p class="text-muted small"><span>Plain text: line breaks are kept. Leave it empty to hide this part.</span></p>
                    </div>
                    <label class="pd-switch"><input type="checkbox" name="show_offers" value="1" @checked(old('show_offers', $content['show_offers']))><i class="pd-switch-track"></i><span>Show the offers of the shop</span></label>
                    <div class="form-group">
                        <label for="offers_title">Title of the offers</label>
                        <input type="text" id="offers_title" name="offers_title" class="form-control" maxlength="80" value="{{ $v('offers_title') }}">
                        <p class="text-muted small"><span>Only shown while the shop is open and has offers on sale.</span></p>
                    </div>
                    <div class="form-group">
                        <label for="footer_text">Text at the bottom</label>
                        <input type="text" id="footer_text" name="footer_text" class="form-control" maxlength="200" value="{{ $v('footer_text') }}">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Highlights</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="features_title">Title of the highlights</label>
                        <input type="text" id="features_title" name="features_title" class="form-control" maxlength="80" value="{{ $v('features_title') }}">
                    </div>
                    @php $features = old('features', $content['features']); @endphp
                    @for ($i = 0; $i < $slots; $i++)
                        @php $feature = $features[$i] ?? ['icon' => 'star', 'title' => '', 'text' => '']; @endphp
                        <div class="pd-slot">
                            <div class="row">
                                <div class="form-group col-xs-4">
                                    <label for="f{{ $i }}-icon">Icon</label>
                                    <select id="f{{ $i }}-icon" name="features[{{ $i }}][icon]" class="form-control">
                                        @foreach ($icons as $icon)
                                            <option value="{{ $icon }}" @selected(($feature['icon'] ?? 'star') === $icon)>{{ ucfirst($icon) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-xs-8">
                                    <label for="f{{ $i }}-title">Title</label>
                                    <input type="text" id="f{{ $i }}-title" name="features[{{ $i }}][title]" class="form-control" maxlength="60" value="{{ $feature['title'] ?? '' }}" placeholder="—">
                                </div>
                            </div>
                            <div class="form-group">
                                <textarea name="features[{{ $i }}][text]" class="form-control" rows="2" maxlength="240" aria-label="Text">{{ $feature['text'] ?? '' }}</textarea>
                            </div>
                        </div>
                    @endfor
                    <p class="text-muted small" style="margin-bottom:0"><span>A highlight without a title is not shown.</span></p>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Games</h3></div>
                <div class="box-body">
                    <div class="form-group" style="margin-bottom:0">
                        <label for="games_title">Title of the games</label>
                        <input type="text" id="games_title" name="games_title" class="form-control" maxlength="80" value="{{ $v('games_title') }}">
                        <p class="text-muted small" style="margin:6px 0 0"><span>One card per category of the shop (or per game), with its price "from". Shown with the offers.</span></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Questions (FAQ)</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="faq_title">Title of the questions</label>
                        <input type="text" id="faq_title" name="faq_title" class="form-control" maxlength="80" value="{{ $v('faq_title') }}">
                    </div>
                    @php $faq = old('faq', $content['faq']); @endphp
                    @for ($i = 0; $i < $faqSlots; $i++)
                        @php $item = $faq[$i] ?? ['q' => '', 'a' => '']; @endphp
                        <div class="pd-slot">
                            <div class="form-group">
                                <input type="text" name="faq[{{ $i }}][q]" class="form-control" maxlength="160" value="{{ $item['q'] ?? '' }}" placeholder="Question" aria-label="Question">
                            </div>
                            <div class="form-group">
                                <textarea name="faq[{{ $i }}][a]" class="form-control" rows="2" maxlength="600" placeholder="Answer" aria-label="Answer">{{ $item['a'] ?? '' }}</textarea>
                            </div>
                        </div>
                    @endfor
                    <p class="text-muted small" style="margin-bottom:0"><span>A question without its answer is not shown. No question: the section is hidden.</span></p>
                </div>
            </div>
        </div>
    </div>
    <div class="box box-primary">
        <div class="box-footer">
            {!! csrf_field() !!}
            <button type="submit" name="reset" value="1" class="btn btn-default btn-sm pull-left" onclick="return confirm('Go back to the original texts? What you wrote is lost.')"><i class="fa fa-undo"></i> <span>Original texts</span></button>
            <button type="submit" class="btn btn-success btn-sm pull-right"><span>Save the page</span></button>
        </div>
    </div>
</form>
@endsection
