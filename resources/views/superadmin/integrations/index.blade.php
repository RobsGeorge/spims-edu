@extends('layouts.app')

@section('title', __('integrations.page_title'))

@section('content')
@php
    $safeByGroup = collect($safe)->groupBy('group');
    $secretsByGroup = collect($secrets)->groupBy('group');
    $oldSafe = old('safe', []);
@endphp
<div class="sa-integrations animate-in">
    <nav class="mb-3 small" aria-label="{{ __('integrations.page_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('integrations.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('integrations.page_title') }}</span>
    </nav>

    <header class="mb-3">
        <h1 class="page-title mb-2">{{ __('integrations.page_title') }}</h1>
        <p class="text-muted-theme mb-2">{{ __('integrations.page_lead') }}</p>
        <p class="small text-muted-theme mb-0">{{ __('integrations.page_help') }}</p>
    </header>

    <aside class="sa-callout sa-callout-danger mb-3" role="note">
        <i class="bi bi-eye-slash-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('integrations.danger_title') }}</strong>
            <p class="mb-0">{{ __('integrations.danger_body') }}</p>
        </div>
    </aside>
    <aside class="sa-callout sa-callout-info mb-4" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('integrations.never_title') }}</strong>
            <p class="mb-0">{{ __('integrations.never_body') }}</p>
        </div>
    </aside>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('superadmin.status') }}">{{ __('integrations.open_status') }}</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('superadmin.config') }}">{{ __('integrations.open_config') }}</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('superadmin.features') }}">{{ __('integrations.open_features') }}</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.finance.index') }}">{{ __('integrations.open_finance') }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.audit.index', ['action' => 'integrations.']) }}">{{ __('integrations.open_audit') }}</a>
    </div>

    <form method="POST" action="{{ route('superadmin.integrations.update') }}" class="mb-4" autocomplete="off">
        @csrf
        @method('PUT')

        @foreach($groups as $group)
            <section class="app-card p-3 mb-4" id="integrations-{{ $group }}" aria-labelledby="integrations-{{ $group }}-heading">
                <h2 class="h6 page-title" id="integrations-{{ $group }}-heading">{{ __('integrations.group_'.$group) }}</h2>
                <p class="small text-muted-theme mb-3">{{ __('integrations.group_'.$group.'_help') }}</p>

                <div class="row g-3">
                    @foreach($safeByGroup->get($group, []) as $row)
                        @php
                            $fieldId = 'safe-'.str_replace('.', '-', $row['key']);
                            $current = array_key_exists($row['key'], $oldSafe) ? $oldSafe[$row['key']] : $row['value'];
                            $fieldName = 'safe['.$row['key'].']';
                            $type = $row['type'];
                            $labelKey = 'integrations.'.\Illuminate\Support\Str::after($row['key'], 'integrations.');
                        @endphp
                        <div class="col-12">
                            @if($type === 'bool')
                                <input type="hidden" name="{{ $fieldName }}" value="0">
                                <div class="form-check">
                                    <input id="{{ $fieldId }}" class="form-check-input" type="checkbox" name="{{ $fieldName }}" value="1" @checked((string) $current === '1' || $current === true || $current === 1)>
                                    <label class="form-check-label" for="{{ $fieldId }}">{{ __($labelKey) }}</label>
                                </div>
                            @else
                                <label class="form-label" for="{{ $fieldId }}">{{ __($labelKey) }}</label>
                                @if($type === 'select')
                                    <select id="{{ $fieldId }}" name="{{ $fieldName }}" class="form-select">
                                        @if(($row['definition']['nullable'] ?? false) && ! in_array('', $row['definition']['options'] ?? [], true))
                                            <option value="">{{ __('integrations.host_default') }}</option>
                                        @endif
                                        @foreach($row['definition']['options'] ?? [] as $option)
                                            @php $optionKey = $option === '' ? '_' : $option; @endphp
                                            <option value="{{ $option }}" @selected((string) $current === (string) $option)>
                                                @if($row['key'] === 'integrations.mail.mailer')
                                                    {{ __('integrations.mailer_'.$option) }}
                                                @elseif($row['key'] === 'integrations.mail.encryption')
                                                    {{ __('integrations.encryption_'.$optionKey) }}
                                                @elseif($row['key'] === 'integrations.paypal.mode')
                                                    {{ __('integrations.mode_'.$option) }}
                                                @else
                                                    {{ $option }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif($type === 'int')
                                    <input id="{{ $fieldId }}" type="number" name="{{ $fieldName }}" class="form-control"
                                           min="{{ $row['definition']['min'] ?? 0 }}"
                                           max="{{ $row['definition']['max'] ?? 999999 }}"
                                           value="{{ $current }}">
                                @elseif($type === 'email')
                                    <input id="{{ $fieldId }}" type="email" name="{{ $fieldName }}" class="form-control"
                                           maxlength="255" value="{{ $current }}" autocomplete="off">
                                @else
                                    <input id="{{ $fieldId }}" name="{{ $fieldName }}" class="form-control"
                                           maxlength="{{ $row['definition']['max'] ?? 255 }}"
                                           value="{{ $current }}" autocomplete="off">
                                @endif
                            @endif
                            <p class="form-text mb-0">
                                {{ __($labelKey.'_help') }}
                                <code>{{ $row['key'] }}</code>
                                @if($row['inherited'])
                                    · {{ __('integrations.inherited') }}
                                @endif
                                @if($row['host_hint'] !== '')
                                    · {{ __('integrations.host_hint') }}
                                @endif
                            </p>
                        </div>
                    @endforeach

                    @foreach($secretsByGroup->get($group, []) as $secret)
                        @php
                            $fieldId = 'secret-'.str_replace('.', '-', $secret['key']);
                            $clearId = $fieldId.'-clear';
                            $envKey = (string) ($secret['env'] ?? '');
                            $secretLabel = 'integrations.'.\Illuminate\Support\Str::after($secret['key'], 'integrations.');
                        @endphp
                        <div class="col-12">
                            <label class="form-label" for="{{ $fieldId }}">
                                {{ __($secretLabel) }}
                                {{ __('integrations.secret_label_suffix') }}
                            </label>
                            <input id="{{ $fieldId }}" type="password" name="secrets[{{ $secret['key'] }}]" class="form-control"
                                   value="" autocomplete="new-password" placeholder="{{ __('integrations.secret_placeholder') }}">
                            <p class="form-text mb-2">
                                {{ __('integrations.secret_help', ['env' => $envKey !== '' ? $envKey : 'host']) }}
                                · <code>{{ $secret['key'] }}</code>
                            </p>
                            <p class="small mb-2">
                                <span class="sa-integration-pill {{ $secret['configured'] ? 'is-on' : 'is-off' }}">
                                    @if($secret['stored'])
                                        {{ __('integrations.secret_stored') }}
                                    @elseif($secret['configured'])
                                        {{ __('integrations.secret_env') }}
                                    @else
                                        {{ __('integrations.secret_missing') }}
                                    @endif
                                </span>
                            </p>
                            @if($secret['stored'])
                                <div class="form-check">
                                    <input id="{{ $clearId }}" class="form-check-input" type="checkbox" name="clear[{{ $secret['key'] }}]" value="1">
                                    <label class="form-check-label" for="{{ $clearId }}">{{ __('integrations.clear_secret') }}</label>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="mb-2">
            <button class="btn btn-primary">{{ __('integrations.save') }}</button>
            <p class="form-text mb-0">{{ __('integrations.save_help') }}</p>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <section class="app-card p-3 h-100" id="integrations-test-mail">
                <h2 class="h6 page-title">{{ __('integrations.test_mail_title') }}</h2>
                <p class="small text-muted-theme mb-3">{{ __('integrations.test_mail_help') }}</p>
                <form method="POST" action="{{ route('superadmin.integrations.test-mail') }}">
                    @csrf
                    <label class="form-label" for="test-mail-identity">{{ __('integrations.test_mail_identity') }}</label>
                    <select id="test-mail-identity" name="identity" class="form-select mb-3">
                        @foreach($identities as $identity)
                            <option value="{{ $identity }}">{{ __('integrations.identity_'.$identity) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-primary">{{ __('integrations.test_mail_send') }}</button>
                </form>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="app-card p-3 h-100" id="integrations-test-payment">
                <h2 class="h6 page-title">{{ __('integrations.test_payment_title') }}</h2>
                <p class="small text-muted-theme mb-3">{{ __('integrations.test_payment_help') }}</p>
                <form method="POST" action="{{ route('superadmin.integrations.test-payment') }}">
                    @csrf
                    <label class="form-label" for="test-payment-gateway">{{ __('integrations.test_payment_gateway') }}</label>
                    <select id="test-payment-gateway" name="gateway" class="form-select mb-3">
                        @foreach($gateways as $gateway)
                            <option value="{{ $gateway }}">{{ __('integrations.gateway_'.$gateway) }}</option>
                        @endforeach
                    </select>
                    <div class="form-check mb-3">
                        <input id="test-payment-confirm" class="form-check-input" type="checkbox" name="confirm" value="1" required>
                        <label class="form-check-label" for="test-payment-confirm">{{ __('integrations.test_payment_confirm') }}</label>
                    </div>
                    <button class="btn btn-outline-danger" onclick="return confirm(@json(__('integrations.test_payment_confirm')))">
                        {{ __('integrations.test_payment_send') }}
                    </button>
                </form>
            </section>
        </div>
    </div>
</div>
@endsection
