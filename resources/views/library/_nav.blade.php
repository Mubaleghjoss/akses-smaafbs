<div class="mb-6 flex flex-wrap gap-2">
    <a @class(['btn', request()->routeIs('library.literacy.*') ? 'btn-primary' : 'btn-secondary']) href="{{ route('library.literacy.index') }}">Literasi Program</a>
    <a @class(['btn', request()->routeIs('library.index') || request()->routeIs('library.show') ? 'btn-primary' : 'btn-secondary']) href="{{ route('library.index') }}">Cari Buku Perpus</a>
    <a @class(['btn', request()->routeIs('library.activities') || request()->routeIs('library.activities.export') ? 'btn-primary' : 'btn-secondary']) href="{{ route('library.activities') }}">Ringkasan Aktivitas Perpus</a>
    <a @class(['btn', request()->routeIs('library.activities.create') ? 'btn-primary' : 'btn-secondary']) href="{{ route('library.activities.create') }}">Form Aktivitas Perpus</a>
    <a @class(['btn', request()->routeIs('library.activities.result') ? 'btn-primary' : 'btn-secondary']) href="{{ route('library.activities.result') }}">Input Hasil Literasi Perpus</a>
</div>
