<style>
    @page { margin: 13mm 14mm 14mm; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        color: #111827;
        font-family: "DejaVu Sans", sans-serif;
        font-size: 9.5px;
        line-height: 1.35;
    }
    .report-page { position: relative; width: 100%; }
    .report-preview-label {
        margin: 0 0 7px;
        border: 1px solid #b45309;
        background: #fffbeb;
        color: #92400e;
        padding: 4px 7px;
        text-align: center;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.05em;
    }
    .report-page--structured {
        min-height: 265mm;
        page-break-after: always;
    }
    .report-page--structured:last-child { page-break-after: auto; }
    .report-watermark {
        position: fixed;
        z-index: -1;
        top: 34%;
        left: 22%;
        width: 56%;
        text-align: center;
    }
    .report-watermark img { width: 100%; height: auto; }
    .report-page--structured .report-watermark {
        position: absolute;
        left: 50%;
        top: 36%;
        transform: translateX(-50%);
    }
    .report-page--structured .report-watermark--top { top: 12%; }
    .report-page--structured .report-watermark--center { top: 36%; }
    .report-page--structured .report-watermark--bottom { top: 66%; }
    .report-page-break { page-break-after: always; }
    .report-page-break:last-child { page-break-after: auto; }
    .letterhead { width: 100%; border-collapse: collapse; margin-bottom: 7px; }
    .letterhead td { vertical-align: middle; border: 0; }
    .letterhead__logo { width: 70px; text-align: center; }
    .letterhead__logo img { max-width: 58px; max-height: 58px; }
    .letterhead__school { text-align: center; }
    .letterhead__school-name { margin: 0 0 2px; font-size: 17px; font-weight: 700; }
    .letterhead__school-info { margin: 0; font-size: 8.5px; }
    .letterhead-rule { margin: 0 0 8px; border: 0; border-top: 2px solid #111827; }
    .report-title { margin: 0; text-align: center; font-size: 14px; font-weight: 700; }
    .report-subtitle { margin: 2px 0 10px; text-align: center; font-size: 9px; }
    .identity { width: 100%; border-collapse: collapse; margin-bottom: 9px; }
    .identity td { padding: 1.5px 3px; border: 0; vertical-align: top; }
    .identity__label { width: 82px; }
    .identity__separator { width: 8px; }
    .scores { width: 100%; border-collapse: collapse; }
    .scores th, .scores td { padding: 4px 5px; border: 1px solid #374151; }
    .scores th { background: #e5e7eb; text-align: center; font-weight: 700; }
    .scores .scores__group td { background: #f3f4f6; font-weight: 700; }
    .scores__number { width: 28px; text-align: center; }
    .scores__score { width: 50px; text-align: center; }
    .scores__predicate { width: 55px; text-align: center; }
    .scores__description { font-size: 8.5px; }
    .section-title { margin: 10px 0 4px; font-size: 10px; font-weight: 700; }
    .summary-table { width: 100%; border-collapse: collapse; }
    .summary-table th, .summary-table td { padding: 4px 5px; border: 1px solid #6b7280; }
    .summary-table th { width: 32%; background: #f3f4f6; text-align: left; }
    .summary-table--attendance th { width: 22%; }
    .summary-table--attendance td { width: 11.333%; text-align: center; white-space: nowrap; }
    .attendance-value { display: inline-block; white-space: nowrap; }
    .report-writing-space { min-height: 34px; height: 34px; vertical-align: top; }
    .report-writing-space--parent { height: 58px; }
    .report-competencies td { page-break-inside: avoid; }
    .empty-row { color: #6b7280; text-align: center; }
    .signatures { width: 100%; border-collapse: collapse; margin-top: 18px; page-break-inside: avoid; }
    .signatures td { width: 33.333%; padding: 0 9px; text-align: center; vertical-align: top; }
    .signature-space { height: 43px; }
    .signature-name { font-weight: 700; text-decoration: underline; }
    .footer-note { margin-top: 8px; color: #6b7280; font-size: 7.5px; text-align: right; }
</style>
