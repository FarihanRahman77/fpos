@extends('pdf.layout')
@php
    $doc = [
        'title'  => 'Expense Invoice',
        'number' => $expense->expense_no,
        'date'   => \Carbon\Carbon::parse($expense->expense_date)->format('d M Y'),
        'meta'   => array_filter(['Reference' => $expense->reference_no ?: null]),
        'signatures' => ['Prepared by' => $preparedBy ?? '', 'Received by' => '', 'Checked by' => '', 'Approved by' => ''],
    ];
@endphp

@section('content')
    @include('pdf.partials.party', ['parties' => [
        ['label' => 'Expense made by', 'kv' => [
            'Employee'      => $expense->employee_name ?: '-',
            'Employee Code' => $expense->employee_code ?: '-',
            'Designation'   => $expense->designation ?: '-',
        ]],
        ['label' => 'Payment details', 'kv' => [
            'Paid From'     => $expense->pa_code . ' - ' . $expense->pa_name,
            'Account Group' => $expense->pg_code . ' - ' . $expense->pg_name,
            'Reference No'  => $expense->reference_no ?: '-',
        ]],
    ]])

    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 30%;">Expense Account</th>
                <th style="width: 20%;">Group</th>
                <th>Description</th>
                <th style="width: 16%;" class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($details as $i => $line)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td>
                        <span class="b">{{ $line->account_name }}</span>
                        <div class="sub">{{ $line->account_code }}</div>
                    </td>
                    <td>{{ $line->group_name }}</td>
                    <td>{{ $line->description ?: '-' }}</td>
                    <td class="num">{{ number_format((float) $line->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('pdf.partials.totals', ['box' => [
        'grand' => ['Total', $total],
        'words' => $total,
        'notes' => ['Note', (string) $expense->remarks],
    ]])
@endsection
