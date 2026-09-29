@extends('admin.master')

@section('title')
{{ $generalSetting['company_name'] ?? '' }} Purchase Return
@endsection

@section('content')

<div class="container-fluid">


<div class="row">

    <div class="col-md-12">

        <div class="card">

            {{-- ============================================================
            | Header
            ============================================================ --}}

            <div class="card-header d-flex justify-content-between align-items-center">

                <div>

                    <h5 class="mb-0">
                        Purchase Return Details
                    </h5>

                </div>

                <div>

                    <a href="{{ route('purchases.return.index') }}"
                       class="btn btn-secondary">

                        <i class="fa fa-arrow-left me-1"></i>

                        Purchase Return List

                    </a>

                    <button type="button"
                            class="btn btn-danger"
                            onclick="window.print()">

                        <i class="fa fa-print me-1"></i>

                        Print

                    </button>

                </div>

            </div>


            <div class="card-body">

                {{-- ========================================================
                | Return Information
                ========================================================= --}}

                <div class="row mb-4">

                    <div class="col-md-6">

                        <table class="table table-bordered mb-0">

                            <tr>

                                <th width="40%">
                                    Return No
                                </th>

                                <td>
                                    {{ $purchaseReturn->return_no }}
                                </td>

                            </tr>

                            <tr>

                                <th>
                                    Return Date
                                </th>

                                <td>
                                    {{ $purchaseReturn->datetime }}
                                </td>

                            </tr>

                            <tr>

                                <th>
                                    Purchase Invoice
                                </th>

                                <td>

                                    {{ $purchaseReturn->invoice_no ?? '-' }}

                                </td>

                            </tr>

                        </table>

                    </div>


                    <div class="col-md-6">

                        <table class="table table-bordered mb-0">

                            <tr>

                                <th width="40%">
                                    Supplier
                                </th>

                                <td>
                                    {{ $purchaseReturn->supplier_name ?? '-' }}
                                </td>

                            </tr>

                            <tr>

                                <th>
                                    Status
                                </th>

                                <td>

                                    @if($purchaseReturn->status == 'Active')

                                        <span class="badge bg-success">
                                            Active
                                        </span>

                                    @else

                                        <span class="badge bg-secondary">
                                            Inactive
                                        </span>

                                    @endif

                                </td>

                            </tr>

                            <tr>

                                <th>
                                    Remarks
                                </th>

                                <td>
                                    {{ $purchaseReturn->remarks ?? '-' }}
                                </td>

                            </tr>

                        </table>

                    </div>

                </div>


                {{-- ========================================================
                | Returned Products
                ========================================================= --}}

                <div class="table-responsive">

                    <table class="table table-bordered table-striped align-middle">

                        <thead>

                            <tr>

                                <th width="5%">
                                    #
                                </th>

                                <th>
                                    Product
                                </th>

                                <th>
                                    Variant
                                </th>

                                <th class="text-end">
                                    Qty
                                </th>

                                <th class="text-end">
                                    Purchase Price
                                </th>

                                <th class="text-end">
                                    Subtotal
                                </th>

                                <th class="text-end">
                                    Discount
                                </th>

                                <th class="text-end">
                                    Total
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @forelse($items as $key => $item)

                                <tr>

                                    <td>
                                        {{ $key + 1 }}
                                    </td>


                                    <td>

                                        {{ $item->product_name ?? '-' }}

                                    </td>


                                    <td>

                                        @if(!empty($item->product_variant_id))

                                            {{ $item->variant_barcode ?? 'Variant #' . $item->product_variant_id }}

                                        @else

                                            -

                                        @endif

                                    </td>


                                    <td class="text-end">

                                        {{ number_format((float) $item->qty, 2) }}

                                    </td>


                                    <td class="text-end">

                                        {{ number_format((float) $item->purchase_price, 2) }}

                                    </td>


                                    <td class="text-end">

                                        {{ number_format((float) $item->subtotal, 2) }}

                                    </td>


                                    <td class="text-end">

                                        {{ number_format((float) $item->discount_amount, 2) }}

                                    </td>


                                    <td class="text-end">

                                        {{ number_format((float) $item->total, 2) }}

                                    </td>

                                </tr>

                            @empty

                                <tr>

                                    <td colspan="8"
                                        class="text-center text-muted">

                                        No return items found.

                                    </td>

                                </tr>

                            @endforelse

                        </tbody>


                        {{-- ====================================================
                        | Summary
                        ===================================================== --}}

                        <tfoot>

                            <tr>

                                <th colspan="7"
                                    class="text-end">

                                    Subtotal

                                </th>

                                <th class="text-end">

                                    {{ number_format((float) $purchaseReturn->subtotal, 2) }}

                                </th>

                            </tr>


                            <tr>

                                <th colspan="7"
                                    class="text-end">

                                    Discount

                                    @if($purchaseReturn->discount_type)

                                        <small class="text-muted">

                                            ({{ $purchaseReturn->discount_type }})

                                        </small>

                                    @endif

                                </th>

                                <th class="text-end">

                                    {{ number_format((float) $purchaseReturn->discount_amount, 2) }}

                                </th>

                            </tr>


                            <tr>

                                <th colspan="7"
                                    class="text-end">

                                    Grand Total

                                </th>

                                <th class="text-end">

                                    {{ number_format((float) $purchaseReturn->grand_total, 2) }}

                                </th>

                            </tr>

                        </tfoot>

                    </table>

                </div>


                {{-- ========================================================
                | Remarks
                ========================================================= --}}

                @if(!empty($purchaseReturn->remarks))

                    <div class="mt-3">

                        <label class="form-label fw-bold">
                            Remarks
                        </label>

                        <div class="border rounded p-3 bg-light">

                            {{ $purchaseReturn->remarks }}

                        </div>

                    </div>

                @endif


                {{-- ========================================================
                | Bottom Buttons
                ========================================================= --}}

                <div class="d-flex justify-content-end mt-4">

                    <a href="{{ route('purchases.return.index') }}"
                       class="btn btn-secondary me-2">

                        <i class="fa fa-arrow-left me-1"></i>

                        Back

                    </a>

                    <button type="button"
                            class="btn btn-danger"
                            onclick="window.print()">

                        <i class="fa fa-print me-1"></i>

                        Print Return

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>


</div>

{{-- ================================================================
| Print CSS
================================================================ --}}

@push('styles')

<style>

@media print {

    body * {
        visibility: hidden;
    }

    .card,
    .card * {
        visibility: visible;
    }

    .card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none !important;
    }

    .card-header .btn,
    .card-body > .d-flex {
        display: none !important;
    }

    .table {
        width: 100% !important;
    }

    .table-bordered {
        border: 1px solid #000 !important;
    }

    .table-bordered th,
    .table-bordered td {
        border: 1px solid #000 !important;
    }

}

</style>

@endpush

@endsection
