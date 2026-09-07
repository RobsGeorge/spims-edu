<h2 class="h6">{{ __('offerings.add_week') }}</h2>
<form method="POST" action="{{ route('admin.offerings.weeks', $offering) }}" class="row g-2 mb-4">
    @csrf
    <div class="col-md-2"><input type="number" name="number" class="form-control" placeholder="#" required></div>
    <div class="col-md-6"><input name="title" class="form-control" placeholder="{{ __('academics.title') }}" required></div>
    <div class="col-md-3"><input type="date" name="unlock_date" class="form-control"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">+</button></div>
</form>
