# Project Rules

## Literacy Numeracy

- Before changing the literacy/numeracy module, read `docs/literacy-numeracy/README.md` and the linked document for the subsystem being changed.
- Keep submit queue work, question types, scoring, similarity analysis, and the school-network monitor compatible with the invariants documented there.

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
