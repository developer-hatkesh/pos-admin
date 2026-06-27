<style>
    @page { size: A4 landscape; margin: 10mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #020617; font-family: Arial, sans-serif; font-size: 13px; }
    .report { padding: 14px; }
    .header { display: grid; grid-template-columns: 1.2fr 1fr 1.2fr; gap: 16px; align-items: start; border-bottom: 1px solid #bfdbfe; padding-bottom: 14px; }
    .brand { display: flex; gap: 14px; align-items: flex-start; }
    .logo { width: 56px; height: 56px; border-radius: 6px; background: #0b4a92; color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 24px; }
    .company-name { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
    .muted { color: #334155; line-height: 1.55; }
    .title { text-align: center; }
    .title h1 { margin: 0 0 12px; font-size: 28px; letter-spacing: 0; text-transform: uppercase; }
    .title .range { font-size: 17px; font-weight: 700; }
    .meta { justify-self: end; display: grid; gap: 8px; font-size: 15px; }
    .meta div { display: grid; grid-template-columns: 70px 12px 1fr; }
    .summary { margin: 16px 0 18px; border: 1px solid #93c5fd; border-radius: 4px; padding: 14px 18px; display: grid; grid-template-columns: 1.2fr 1fr 1.2fr; gap: 12px 32px; font-size: 15px; }
    .summary div { display: grid; grid-template-columns: 140px 12px 1fr; gap: 8px; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th { background: #073b82; color: #fff; padding: 11px 10px; border: 1px solid #5b83bd; font-size: 14px; }
    td { padding: 10px; border: 1px solid #d7dee8; vertical-align: middle; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    .num { text-align: right; white-space: nowrap; }
    .center { text-align: center; }
    .strong { font-weight: 700; }
    tfoot td { background: #dbeafe; font-weight: 700; }
    .footer { margin-top: 18px; display: flex; justify-content: space-between; font-size: 13px; }
    .print-actions { margin: 12px 0; text-align: right; }
    .print-actions button { background: #073b82; color: #fff; border: 0; border-radius: 4px; padding: 8px 14px; cursor: pointer; }
    @media print {
        .print-actions { display: none; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
