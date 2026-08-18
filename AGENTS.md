# Project Rules

## Literacy Numeracy

- Before changing the literacy/numeracy module, read `docs/literacy-numeracy/README.md` and the linked document for the subsystem being changed.
- Keep submit queue work, question types, scoring, similarity analysis, and the school-network monitor compatible with the invariants documented there.

## Penilaian ASTS–ASAS

- Before changing the assessment module, read `docs/assessment/README.md` and the linked document for the subsystem being changed.
- Preserve period-scoped data, immutable report snapshots, optimistic locking, private PDF storage, explicit action authorization, and Literasi-first queue priority.
- Do not use `guru_mapel_label`, `guru_walas_scope`, legacy `kelas`, or `boarding_rapots` as an assessment transaction source.
- Never expose report files through the public disk or webroot.

## Admin UI responsiveness

- Every admin feature in `/admin` must be usable on phone-width screens.
- “Usable” means: no page-level horizontal overflow, primary actions remain reachable, forms remain readable, and CRUD modals stay inside the viewport.
- New multi-column admin forms must default to one column on small screens and only expand at larger breakpoints.
- New admin tables must define a mobile behavior: wrapped actions, scroll-safe content, and low-priority columns hidden, toggleable, or otherwise de-emphasized on narrow screens.
- New custom Filament pages/widgets must prefer shared panel styling and shared conventions over one-off CSS hacks.
- Any admin UI update is incomplete until mobile behavior has been checked for list, form, modal, and empty/loading states.

## Admin dashboard chart behavior

- Interactive admin charts must use a consistent click behavior: clicking a chart segment/bar should open the relevant admin list already filtered to the clicked data point.
- If a chart does not have a meaningful filtered-list destination, keep it explicitly informational instead of forcing weak drilldown behavior.
- Prefer stable, page-owned query parameters or page-owned state translation over fragile direct widget hacks when applying chart-driven filters.
- Admin pages with multiple charts should provide a consistent shared control for showing/hiding diagram sections when the page density would otherwise feel noisy.
- New dashboard/chart work is incomplete until chart clicks and filtered landing behavior have been manually verified in the target admin page.

## Change policy

- Prefer shared Filament-compatible solutions first.
- Do not edit vendor files to achieve responsiveness.
- If a screen needs a special mobile treatment, keep it local and explain why the shared pattern was not enough.

## Hotspot MikroTik (hasil-hermes integration)

- Kredensial router: env `HOTSPOT_MT_*` (~/.env lokal, JANGAN di-commit).
- Service: `App\Services\RouterOS` (klien API), `HotspotManager` (user/profil), `HotspotBlocker` (blokir/kesehatan).
- Menu di PANEL ADMIN (`/admin`), grup **Manajemen Sekolah → parent "IT SMA AFBS"**:
  `Monitor` (halaman), `HotspotUserResource` (Akun Hotspot), `BlockedDomainResource` (Blokir Situs) —
  mapping ada di `App\Support\Admin\AdminSchoolNavigation` (CLASS_PARENT_MAP).
- AKSES: hanya role admin penuh (admin/guru_admin/super_admin) atau user yang diberi
  item ini lewat kolom `allowed_navigation_items` di UserResource. Gate: trait
  `App\Support\Hotspot\HotspotAccessible` (canViewAny/canAccess).
- Router = sumber kebenaran akun; tabel `hotspot_users` adalah MIRROR lokal (pola Mikhmon).
- Address-list `blocklist` + komentar firewall `hasil-hermes-block/-dns-lock/-dns-lock2` (config/hotspot.php).
- Migrasi integrasi: `database/migrations/2026_08_18_0000*` — jangan diubah tanpa perlu.
