<style>
    @page { size: A4 portrait; margin: 9mm 11mm 13mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #111827; font-family: "DejaVu Sans", sans-serif; font-size: 9.4px; line-height: 1.28; }
    .report-page { position: relative; width: 100%; }
    .report-page--scores { page-break-inside: avoid; }
    .report-page-break { page-break-after: always; height: 0; }
    .report-footer { position: fixed; bottom: -8mm; left: 0; right: 0; color: #4b5563; font-size: 8px; text-align: center; }
    .letterhead { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .letterhead td { vertical-align: middle; border: 0; }
    .letterhead__logo { width: 62px; text-align: center; }
    .letterhead__logo img { max-width: 52px; max-height: 52px; }
    .letterhead__school { text-align: center; }
    .letterhead__school-name { margin: 0 0 1px; font-size: 16.5px; font-weight: 700; }
    .letterhead__school-info { margin: 0; font-size: 8.2px; }
    .letterhead-rule { margin: 0 0 7px; border: 0; border-top: 1.5px solid #111827; }
    .report-title { margin: 0; text-align: center; font-size: 13.2px; font-weight: 700; }
    .report-subtitle { margin: 2px 0 8px; text-align: center; font-size: 9px; }
    .identity { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .identity td { padding: 1.5px 3px; border: 0; vertical-align: top; }
    .identity__label { width: 82px; } .identity__separator { width: 7px; }
    .scores, .summary-table { width: 100%; border-collapse: collapse; }
    .scores th, .scores td, .summary-table th, .summary-table td { padding: 3.5px 4.5px; border: 1px solid #4b5563; vertical-align: top; }
    .scores th { background: #e5e7eb; text-align: center; font-weight: 700; }
    .scores tr { page-break-inside: avoid; }
    .scores__number { width: 27px; text-align: center; } .scores__score { width: 50px; text-align: center; }
    .scores__predicate { width: 55px; text-align: center; } .scores__description { font-size: 8.4px; line-height: 1.2; }
    .empty-row { color: #6b7280; text-align: center; }
    .section-title { margin: 7px 0 3px; font-size: 10px; font-weight: 700; }
    .summary-table th { width: 27%; background: #f3f4f6; text-align: left; }
    .summary-table--attendance td { white-space: nowrap; }
    .attendance-value { display: inline-block; white-space: nowrap; }
    .report-writing-space { height: 46px; max-height: 46px; overflow: hidden; vertical-align: top; }
    .signatures { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 14px; page-break-inside: avoid; }
    .signatures td { width: 33.333%; padding: 0 6px; text-align: center; vertical-align: top; }
    .signature-date { padding-bottom: 2px !important; }
    .signature-labels td { height: 16px; font-weight: 400; }
    .signature-spaces td { padding-top: 0; padding-bottom: 0; }
    .signature-space { height: 42px; }
    .signature-names td { vertical-align: bottom; }
    .signature-name { display: block; min-height: 14px; border-bottom: 1px solid #111827; font-weight: 700; white-space: nowrap; }
    .signature-name--blank { font-weight: 400; letter-spacing: 0.5px; }
    .signature-identifier { min-height: 12px; }

    .letterhead--asts { margin-bottom: 2px; }
    .letterhead--asts .letterhead__logo { width: 58px; }
    .letterhead--asts .letterhead__logo img { max-width: 48px; max-height: 48px; }
    .letterhead__foundation { margin: 0; font-size: 11px; font-weight: 700; }
    .letterhead--asts .letterhead__school-name { font-size: 15px; }
    .letterhead-rule--asts { border-top-width: 2.5px; margin-bottom: 7px; }
    .report-page--asts-scores .report-subtitle { margin-bottom: 6px; }
    .asts-kktp-title { margin: 0 0 3px; font-size: 9.5px; font-weight: 700; }
    .asts-kktp { width: 100%; border-collapse: collapse; margin-bottom: 7px; text-align: center; }
    .asts-kktp th, .asts-kktp td { padding: 2.5px 4px; border: 1px solid #4b5563; }
    .asts-kktp th { background: #e5e7eb; }
    .asts-kktp th span { font-size: 7.6px; font-weight: 400; }
    .asts-subject-group { margin: 6px 0 2px; font-size: 9.5px; font-weight: 700; }
    .asts-scores { margin-bottom: 4px; }
    .asts-summary-grid { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 10px; }
    .asts-summary-grid > tbody > tr > td { width: 50%; padding: 0 5px; vertical-align: top; }
    .asts-summary-grid > tbody > tr > td:first-child { padding-left: 0; }
    .asts-summary-grid > tbody > tr > td:last-child { padding-right: 0; }
    .asts-summary-grid .section-title { margin-top: 0; }
    .asts-extracurricular th, .asts-extracurricular td { text-align: center; }
    .asts-extracurricular th:nth-child(2), .asts-extracurricular td:nth-child(2) { text-align: left; }
    .asts-extracurricular th:first-child { width: 27px; }
    .asts-extracurricular th:last-child { width: 48px; }
    .asts-signatures { margin-top: 34px; }
    .asts-signatures td { width: 50%; }
</style>
