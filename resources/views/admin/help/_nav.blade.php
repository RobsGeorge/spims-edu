@php
    $helpAdminTab = $helpAdminTab ?? 'articles';
@endphp
<ul class="nav nav-pills gap-2 mb-4 flex-wrap">
    <li class="nav-item">
        <a class="nav-link {{ $helpAdminTab === 'articles' ? 'active' : '' }}"
           href="{{ route('admin.help.articles.index') }}">{{ __('help.admin_articles') }}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $helpAdminTab === 'categories' ? 'active' : '' }}"
           href="{{ route('admin.help.categories.index') }}">{{ __('help.admin_categories') }}</a>
    </li>
</ul>
