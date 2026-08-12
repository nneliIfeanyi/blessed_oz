<?php
session_start();
if (!isset($_SESSION['loggedIn'])) {
    header('Location: login.php');
    exit();
}

require_once('inc/config/constants.php');
require_once('inc/config/db.php');
require_once('inc/store.php');
require_once('inc/header.html');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

function formatCurrencyAmount($value)
{
    return number_format((float) $value, 2);
}

$creditors = [];
$pageError = '';

try {
    $creditorsSql = 'SELECT sh.saleReference, sh.customerID, sh.customerName, sh.saleDate, ROUND(COALESCE(SUM(si.lineTotal), 0), 2) AS amountDue, ROUND(COALESCE(sh.amountPaid, 0), 2) AS amountPaid, ROUND(COALESCE(SUM(si.lineTotal), 0) - COALESCE(sh.amountPaid, 0), 2) AS balance FROM sale_headers sh LEFT JOIN sale_items si ON si.saleReference = sh.saleReference AND si.storeID = sh.storeID WHERE sh.storeID = :storeID GROUP BY sh.id, sh.saleReference, sh.customerID, sh.customerName, sh.saleDate, sh.amountPaid HAVING balance > 0 ORDER BY sh.saleDate DESC, sh.id DESC';
    $creditorsStatement = $conn->prepare($creditorsSql);
    $creditorsStatement->execute(['storeID' => $activeStoreID]);
    $creditors = $creditorsStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pageError = 'Creditor data is unavailable right now.';
}
?>

<body>
    <?php require 'inc/navigation.php'; ?>

    <div class="container" style="margin-top:100px;">
        <div id="creditorsPageMessage"></div>
        <div class="card card-outline-secondary my-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>All Creditors</span>
                <div>
                    <a href="index.php#v-pills-credit" class="btn btn-sm btn-outline-primary">Back to Credit Book</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($pageError != '') {
                    echo '<div class="alert alert-warning">' . htmlspecialchars($pageError) . '</div>';
                } ?>

                <div class="table-responsive">
                    <table id="creditorsListTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Customer Name</th>
                                <th>Amount Due</th>
                                <th>Amount Paid</th>
                                <th>Balance</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($creditors) > 0) {
                                foreach ($creditors as $creditor) {
                                    $saleReference = (string) $creditor['saleReference'];
                                    $customerName = (string) $creditor['customerName'];
                                    $amountDue = (float) $creditor['amountDue'];
                                    $amountPaid = (float) $creditor['amountPaid'];
                                    $balance = (float) $creditor['balance'];

                                    echo '<tr data-sale-reference="' . htmlspecialchars($saleReference) . '">' .
                                        '<td>' . htmlspecialchars($saleReference) . '</td>' .
                                        '<td>' . htmlspecialchars($customerName) . '</td>' .
                                        '<td class="credit-amount-due">' . htmlspecialchars(formatCurrencyAmount($amountDue)) . '</td>' .
                                        '<td class="credit-amount-paid">' . htmlspecialchars(formatCurrencyAmount($amountPaid)) . '</td>' .
                                        '<td class="credit-balance">' . htmlspecialchars(formatCurrencyAmount($balance)) . '</td>' .
                                        '<td>' . htmlspecialchars((string) $creditor['saleDate']) . '</td>' .
                                        '<td><button type="button" class="btn btn-sm btn-outline-success edit-creditor-btn" data-toggle="modal" data-target="#creditorPaymentModal" data-sale-reference="' . htmlspecialchars($saleReference) . '" data-customer-id="' . htmlspecialchars((string) $creditor['customerID']) . '" data-customer-name="' . htmlspecialchars($customerName) . '" data-amount-due="' . htmlspecialchars((string) $amountDue) . '" data-amount-paid="' . htmlspecialchars((string) $amountPaid) . '" data-balance="' . htmlspecialchars((string) $balance) . '">Edit</button></td>' .
                                        '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-muted">No outstanding creditors found.</td></tr>';
                            } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="creditorPaymentModal" tabindex="-1" role="dialog" aria-labelledby="creditorPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="creditorPaymentModalLabel">Update Creditor Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="creditorPaymentForm">
                    <div class="modal-body">
                        <div id="creditorPaymentMessage"></div>
                        <input type="hidden" id="creditorModalCustomerID" name="customerID">
                        <input type="hidden" id="creditorModalSaleReference" name="saleReference">
                        <div class="form-group">
                            <label for="creditorModalCustomerName">Customer Name</label>
                            <input type="text" class="form-control" id="creditorModalCustomerName" readonly>
                        </div>
                        <div class="form-group">
                            <label for="creditorModalTransactionID">Transaction ID</label>
                            <input type="text" class="form-control" id="creditorModalTransactionID" readonly>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="creditorModalAmountDue">Amount Due</label>
                                <input type="text" class="form-control" id="creditorModalAmountDue" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="creditorModalAmountPaid">Amount Paid</label>
                                <input type="text" class="form-control" id="creditorModalAmountPaid" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="creditorModalBalance">Balance</label>
                                <input type="text" class="form-control" id="creditorModalBalance" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="creditorAdditionalPayment">Add Payment</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="creditorAdditionalPayment" name="paymentAmount" placeholder="Enter new payment amount" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" id="submitCreditorPaymentButton" class="btn btn-success">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php require 'inc/footer.php'; ?>

    <script>
        function formatCreditAmount(value) {
            var number = parseFloat(value || 0);
            if (isNaN(number)) {
                number = 0;
            }
            return number.toFixed(2);
        }

        function parseAmount(value) {
            if (typeof value === 'string') {
                value = value.replace(/[^0-9.-]/g, '');
            }
            var parsed = parseFloat(value);
            return isNaN(parsed) ? 0 : parsed;
        }

        $(function() {
            if ($.fn.DataTable.isDataTable('#creditorsListTable') === false) {
                $('#creditorsListTable').DataTable({
                    dom: 'lBfrtip',
                    order: [
                        [5, 'desc']
                    ],
                    buttons: [
                        'copy',
                        {
                            extend: 'csv',
                            footer: true,
                            title: 'Creditors List'
                        },
                        {
                            extend: 'excel',
                            footer: true,
                            title: 'Creditors List'
                        },
                        {
                            extend: 'pdf',
                            footer: true,
                            orientation: 'landscape',
                            pageSize: 'LEGAL',
                            title: 'Creditors List',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5]
                            }
                        },
                        {
                            extend: 'print',
                            footer: true,
                            title: 'Creditors List',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5]
                            }
                        }
                    ],
                    footerCallback: function(row, data, start, end, display) {
                        var api = this.api();

                        var dueTotal = api.column(2).data().reduce(function(a, b) {
                            return parseAmount(a) + parseAmount(b);
                        }, 0);
                        var dueFilteredTotal = api.column(2, {
                            page: 'current'
                        }).data().reduce(function(a, b) {
                            return parseAmount(a) + parseAmount(b);
                        }, 0);

                        var paidTotal = api.column(3).data().reduce(function(a, b) {
                            return parseAmount(a) + parseAmount(b);
                        }, 0);
                        var paidFilteredTotal = api.column(3, {
                            page: 'current'
                        }).data().reduce(function(a, b) {
                            return parseAmount(a) + parseAmount(b);
                        }, 0);

                        var balanceTotal = api.column(4).data().reduce(function(a, b) {
                            return parseAmount(a) + parseAmount(b);
                        }, 0);
                        var balanceFilteredTotal = api.column(4, {
                            page: 'current'
                        }).data().reduce(function(a, b) {
                            return parseAmount(a) + parseAmount(b);
                        }, 0);

                        $(api.column(2).footer()).html(formatCreditAmount(dueFilteredTotal) + ' (' + formatCreditAmount(dueTotal) + ' total)');
                        $(api.column(3).footer()).html(formatCreditAmount(paidFilteredTotal) + ' (' + formatCreditAmount(paidTotal) + ' total)');
                        $(api.column(4).footer()).html(formatCreditAmount(balanceFilteredTotal) + ' (' + formatCreditAmount(balanceTotal) + ' total)');
                    }
                });
            }

            $(document).on('click', '.edit-creditor-btn', function() {
                var $button = $(this);

                $('#creditorModalCustomerID').val($button.data('customer-id'));
                $('#creditorModalSaleReference').val($button.data('sale-reference'));
                $('#creditorModalCustomerName').val($button.data('customer-name'));
                $('#creditorModalTransactionID').val($button.data('sale-reference'));
                $('#creditorModalAmountDue').val(formatCreditAmount($button.data('amount-due')));
                $('#creditorModalAmountPaid').val(formatCreditAmount($button.data('amount-paid')));
                $('#creditorModalBalance').val(formatCreditAmount($button.data('balance')));
                $('#creditorAdditionalPayment').val('');
                $('#creditorPaymentForm').data('triggerButton', $button);
                $('#creditorPaymentMessage').empty();
            });

            $('#creditorPaymentForm').on('submit', function(event) {
                event.preventDefault();

                var $button = $('#creditorPaymentForm').data('triggerButton');
                var paymentAmount = parseFloat($('#creditorAdditionalPayment').val() || 0);
                var currentBalance = parseFloat($('#creditorModalBalance').val() || 0);

                if (!(paymentAmount > 0)) {
                    showToastMessage('Enter a valid payment amount.', 'creditorPaymentMessage');
                    return;
                }

                if (paymentAmount - currentBalance > 0.009) {
                    showToastMessage('Payment amount cannot be greater than the current balance.', 'creditorPaymentMessage');
                    return;
                }

                $('#submitCreditorPaymentButton').prop('disabled', true);

                $.ajax({
                    url: 'model/customer/updateCreditTransaction.php',
                    method: 'POST',
                    data: {
                        customerID: $('#creditorModalCustomerID').val(),
                        saleReference: $('#creditorModalSaleReference').val(),
                        paymentAmount: paymentAmount
                    },
                    success: function(data) {
                        try {
                            var result = typeof data === 'string' ? $.parseJSON(data) : data;
                            showToastMessage(result.message, 'creditorsPageMessage');

                            if (result.success) {
                                var dataTable = $('#creditorsListTable').DataTable();
                                var $row = $button.closest('tr');

                                if (result.removeRow) {
                                    dataTable.row($row).remove().draw(false);
                                } else {
                                    $row.find('.credit-amount-paid').text(formatCreditAmount(result.amountPaid));
                                    $row.find('.credit-balance').text(formatCreditAmount(result.balance));
                                    $button.attr('data-amount-paid', result.amountPaid);
                                    $button.attr('data-balance', result.balance);
                                    $button.data('amount-paid', result.amountPaid);
                                    $button.data('balance', result.balance);
                                }

                                $('#creditorPaymentModal').modal('hide');
                            } else {
                                showToastMessage(result.message, 'creditorPaymentMessage');
                            }
                        } catch (e) {
                            showToastMessage('Unable to update creditor payment.', 'creditorPaymentMessage');
                        }
                    },
                    error: function() {
                        showToastMessage('Unable to update creditor payment.', 'creditorPaymentMessage');
                    },
                    complete: function() {
                        $('#submitCreditorPaymentButton').prop('disabled', false);
                    }
                });
            });
        });
    </script>
</body>

</html>