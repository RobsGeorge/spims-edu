<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('import.claim_mail_subject') }}</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f1ea; font-family: Tahoma, Arial, sans-serif; color: #2b2620; }
        .claim-wrap { padding: 24px 0; }
        .claim-card { background: #ffffff; border-radius: 8px; padding: 32px; max-width: 480px; margin: 0 auto; text-align: {{ $rtl ? 'right' : 'left' }}; }
        .claim-heading { font-size: 18px; margin: 0 0 16px; }
        .claim-p { font-size: 14px; line-height: 1.6; margin: 0 0 12px; }
        .claim-cta-wrap { text-align: center; margin: 0 0 20px; }
        .claim-cta { display: inline-block; background: #7a3b2e; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; }
        .claim-code { font-size: 20px; letter-spacing: 2px; }
        .claim-hint { font-size: 12px; line-height: 1.6; color: #6b6459; margin: 0 0 20px; }
        .claim-footer { font-size: 12px; line-height: 1.6; color: #6b6459; margin: 0; }
        .claim-rule { border: none; border-top: 1px solid #e5e0d5; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="claim-wrap">
        <div class="claim-card">
            <h1 class="claim-heading">{{ __('import.claim_mail_heading') }}</h1>
            <p class="claim-p">{{ __('import.claim_mail_greeting', ['name' => $recipientName]) }}</p>
            <p class="claim-p">{{ __('import.claim_mail_intro') }}</p>
            <p class="claim-cta-wrap">
                <a href="{{ $claimUrl }}" class="claim-cta">{{ __('import.claim_mail_cta') }}</a>
            </p>
            <p class="claim-p">
                {{ __('import.claim_mail_code_label') }}
                <strong class="claim-code">{{ $code }}</strong>
            </p>
            <p class="claim-hint">{{ __('import.claim_mail_link_hint') }}</p>
            <hr class="claim-rule">
            <p class="claim-footer">{{ __('import.claim_mail_footer') }}</p>
        </div>
    </div>
</body>
</html>
