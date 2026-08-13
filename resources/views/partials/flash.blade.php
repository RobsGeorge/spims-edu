@php
    $errorBag = isset($errors) ? $errors : null;
    $hasFlash = session()->has('status') || session()->has('warning') || session()->has('error') || ($errorBag && $errorBag->any());
@endphp
@if($hasFlash)
    <div class="spims-flash mb-3" role="region" aria-label="{{ __('ui.nav_notifications') }}">
        @if(session('status'))
            <div class="alert alert-success spims-toast" role="status" data-spims-toast>
                {{ session('status') }}
            </div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning spims-toast" role="status" data-spims-toast>
                {{ session('warning') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger spims-toast" role="alert" data-spims-toast>
                {{ session('error') }}
            </div>
        @endif
        @if($errorBag && $errorBag->any())
            <div class="alert alert-danger spims-toast" role="alert" data-spims-toast>
                <ul class="mb-0 ps-3">
                    @foreach($errorBag->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
