@php($title = 'Masuk Pelanggan · Younz Digital Center')
@extends('layouts.public')

@section('content')
    @include('customer.auth.account-form', ['authMode' => 'login'])
@endsection
