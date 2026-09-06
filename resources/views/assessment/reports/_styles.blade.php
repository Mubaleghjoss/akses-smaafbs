<style>
    @page { size: A4 portrait; margin: 10mm 12mm 15mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #111827; font-family: "DejaVu Sans", sans-serif; font-size: 8.5px; line-height: 1.2; }
    .report-page { position: relative; width: 100%; }
    .report-page--scores { page-break-inside: avoid; }
    .report-page-break { page-break-after: always; height: 0; }
    .report-footer { position: fixed; bottom: -9mm; left: 0; right: 0; color: #4b5563; font-size: 7.5px; text-align: center; }
    .letterhead { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    .letterhead td { vertical-align: middle; border: 0; }
    .letterhead__logo { width: 55px; text-align: center; }
    .letterhead__logo img { max-width: 45px; max-height: 45px; }
    .letterhead__school { text-align: center; }
    .letterhead__school-name { margin: 0 0 1px; font-size: 14px; font-weight: 700; }
    .letterhead__school-info { margin: 0; font-size: 7.5px; }
    .letterhead-rule { margin: 0 0 5px; border: 0; border-top: 1.5px solid #111827; }
    .report-title { margin: 0; text-align: center; font-size: 11px; font-weight: 700; }
    .report-subtitle { margin: 2px 0 6px; text-align: center; font-size: 8px; }
    .identity { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .identity td { padding: 1px 2px; border: 0; vertical-align: top; }
    .identity__label { width: 70px; } .identity__separator { width: 7px; }
    .scores, .summary-table { width: 100%; border-collapse: collapse; }
    .scores th, .scores td, .summary-table th, .summary-table td { padding: 2.5px 3px; border: 1px solid #4b5563; vertical-align: top; }
    .scores th { background: #e5e7eb; text-align: center; font-weight: 700; }
    .scores tr { page-break-inside: avoid; }
    .scores__number { width: 22px; text-align: center; } .scores__score { width: 42px; text-align: center; }
    .scores__predicate { width: 48px; text-align: center; } .scores__description { font-size: 7.5px; line-height: 1.15; }
    .empty-row { color: #6b7280; text-align: center; }
    .section-title { margin: 5px 0 2px; font-size: 9px; font-weight: 700; }
    .summary-table th { width: 27%; background: #f3f4f6; text-align: left; }
    .summary-table--attendance th { width: 18%; } .summary-table--attendance td { width: 15.333%; text-align: center; white-space: nowrap; }
    .attendance-value { display: inline-block; white-space: nowrap; }
    .report-writing-space { height: 38px; max-height: 38px; overflow: hidden; vertical-align: top; }
    .signatures { width: 100%; border-collapse: collapse; margin-top: 10px; page-break-inside: avoid; }
    .signatures td { width: 33.333%; padding: 0 6px; text-align: center; vertical-align: top; }
    .signature-space { height: 30px; } .signature-name { font-weight: 700; text-decoration: underline; }
</style>
