@php
    $serial = $credential?->serial ?? ($previewSerial ?? '');
    $issuedAt = $credential?->issued_at?->toDateString() ?? ($previewIssuedAt ?? '');
    $verifyUrl = $credential?->verifyUrl();
    $signatoryName = $credential?->signatory_name;
    $signatoryTitle = $credential?->signatory_title;
    $isLegacy = $credential?->isLegacy() ?? false;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $serial }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #1a1a1a; text-align: {{ $isRtl ? 'right' : 'left' }}; }
        .certificate { border: 4px solid #7a5230; padding: 3rem; text-align: center; }
        h1 { font-size: 1.75rem; margin-bottom: 1.5rem; }
        .body-text { font-size: 1.1rem; line-height: 1.8; margin-bottom: 2rem; white-space: pre-line; }
        .serial { font-size: 0.85rem; color: #555; }
        dl { display: grid; grid-template-columns: 10rem 1fr; gap: 0.35rem 1rem; margin-top: 1.5rem; text-align: {{ $isRtl ? 'right' : 'left' }}; }
        dt { font-weight: 600; }
        dd { margin: 0; }
        .historical-banner { background: #7a5230; color: #fff; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.08em; text-transform: uppercase; padding: 0.5rem 1rem; margin-bottom: 1.5rem; text-align: center; }
        .historical-notice { border: 1px solid #7a5230; color: #5a3a10; background: #fdf3e7; font-size: 0.8rem; padding: 0.6rem 1rem; margin-bottom: 1.5rem; text-align: {{ $isRtl ? 'right' : 'left' }}; }
    </style>
</head>
<body>
    @if($isLegacy)
        <div class="historical-banner">{{ __('credentials.historical_download_banner', [], $locale) }}</div>
        <div class="historical-notice">{{ __('credentials.historical_download_notice', [], $locale) }}</div>
    @endif
    <div class="certificate">
        <h1>{{ $title }}</h1>
        <p class="body-text">{{ $body }}</p>
        <dl>
            <dt>{{ __('credentials.serial') }}</dt>
            <dd>{{ $serial }}</dd>
            <dt>{{ __('credentials.issued_at') }}</dt>
            <dd>{{ $issuedAt }}</dd>
            @if($signatoryName)
                <dt>{{ __('credentials.signatory') }}</dt>
                <dd>{{ $signatoryName }} @if($signatoryTitle) — {{ $signatoryTitle }} @endif</dd>
            @endif
        </dl>
        @if($verifyUrl)
            <p class="serial">{{ $verifyUrl }}</p>
        @endif
    </div>
</body>
</html>
