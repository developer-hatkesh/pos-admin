@include('documents.print', [
    'document' => $invoice,
    'title' => 'Sales Invoice',
    'numberColumn' => 'invoice_no',
    'dateColumn' => 'invoice_date',
    'dueDateColumn' => 'due_date',
    'partyRelation' => 'customer',
    'paid' => $paidAmount,
    'due' => $dueAmount,
    'totals' => $invoiceTotals,
    'receiptSettings' => $receiptSettings,
])
