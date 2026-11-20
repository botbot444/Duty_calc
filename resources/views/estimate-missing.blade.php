@extends('layouts.app')

@section('title', __('Your estimate').' – '.__('DutyCalc'))

@section('content')
    <h1>{{ __('Your estimate') }}</h1>
    <section class="card">
        <p class="strong">{{ __('This estimate has expired or does not exist.') }}</p>
        <p class="hint">{{ __('Keep this link to see this estimate again for :days days.', ['days' => config('dutycalc.estimate_retention_days')]) }}</p>
        <a class="btn btn-primary" href="{{ route('home') }}?new=1">{{ __('New estimate') }}</a>
    </section>
@endsection
