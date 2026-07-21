@extends('layouts.app')

@section('content')
    <div style="max-width: 420px; margin: 40px auto; text-align: center;">
        <p>본인인증 페이지로 이동합니다...</p>
        <form id="identity-verification-form" method="POST" action="{{ $identityRequest->actionUrl }}">
            @foreach ($identityRequest->formParams as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
        </form>
    </div>
    <script>
        document.getElementById('identity-verification-form').submit();
    </script>
@endsection
