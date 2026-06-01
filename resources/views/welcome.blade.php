@extends('layouts.landing')

@section('title', 'FutGO · El sistema operativo del fútbol amateur')

@section('content')
    <x-landing.hero />
    <x-landing.social-proof />
    <x-landing.features />
    <x-landing.ecosystem />
    <x-landing.testimonials />
    <x-landing.cta />
@endsection
