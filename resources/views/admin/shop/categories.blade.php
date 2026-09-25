@extends('layouts.admin')

@section('title')
    Shop categories
@endsection

@section('content-header')
    <h1>Categories<small>Group your offers (Minecraft, FiveM, bots...).</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.shop.offers') }}">Shop</a></li>
        <li class="active">Categories</li>
    </ol>
@endsection

@section('content')
@php $canManage = auth()->user()->hasAdminPermission('shop.manage'); @endphp
<div class="row">
    <div class="col-xs-12">
        @include('admin.shop._nav')
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>
        @endif
    </div>
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Categories</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr><th>Name</th><th style="width:110px">Position</th><th>Offers</th><th></th></tr>
                        @forelse ($categories as $category)
                            <tr>
                                <td>
                                    @if ($canManage)
                                        <input type="text" name="name" form="pd-cat-{{ $category->id }}" class="form-control input-sm" maxlength="60" value="{{ $category->name }}" required>
                                    @else
                                        <strong>{{ $category->name }}</strong>
                                    @endif
                                </td>
                                <td>
                                    @if ($canManage)
                                        <input type="number" name="position" form="pd-cat-{{ $category->id }}" class="form-control input-sm" min="0" value="{{ $category->position }}">
                                    @else
                                        {{ $category->position }}
                                    @endif
                                </td>
                                <td>{{ $category->offers_count }}</td>
                                <td class="text-right" style="white-space:nowrap">
                                    @if ($canManage)
                                        <button type="submit" form="pd-cat-{{ $category->id }}" class="btn btn-xs btn-primary"><i class="fa fa-save"></i></button>
                                        <button type="submit" form="pd-cat-del-{{ $category->id }}" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted" style="padding:28px"><span>No category yet. Create the first one on the right.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($canManage)
            @foreach ($categories as $category)
                <form id="pd-cat-{{ $category->id }}" action="{{ route('admin.shop.categories.save', $category->id) }}" method="POST">{!! csrf_field() !!}</form>
                <form id="pd-cat-del-{{ $category->id }}" action="{{ route('admin.shop.categories.delete', $category->id) }}" method="POST" onsubmit="return confirm('Delete this category? Its offers stay on sale, without a category.')">{!! csrf_field() !!}{!! method_field('DELETE') !!}</form>
            @endforeach
        @endif
    </div>
    @if ($canManage)
        <div class="col-md-4">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">New category</h3></div>
                <form action="{{ route('admin.shop.categories') }}" method="POST">
                    <div class="box-body">
                        <div class="form-group">
                            <label for="new-name">Name</label>
                            <input type="text" id="new-name" name="name" class="form-control" maxlength="60" placeholder="Minecraft" required>
                        </div>
                        <div class="form-group">
                            <label for="new-position">Position</label>
                            <input type="number" id="new-position" name="position" class="form-control" min="0" value="0">
                            <p class="text-muted small"><span>The smallest number is shown first.</span></p>
                        </div>
                    </div>
                    <div class="box-footer">{!! csrf_field() !!}<button type="submit" class="btn btn-success btn-sm pull-right"><i class="fa fa-plus"></i> <span>Create</span></button></div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
