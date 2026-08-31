// File that creates the purchase details search table
purchaseDetailsSearchTableCreatorFile = 'model/purchase/purchaseDetailsSearchTableCreator.php';

// File that creates the customer details search table
customerDetailsSearchTableCreatorFile = 'model/customer/customerDetailsSearchTableCreator.php';

// File that creates the item details search table
itemDetailsSearchTableCreatorFile = 'model/item/itemDetailsSearchTableCreator.php';

// File that creates the vendor details search table
vendorDetailsSearchTableCreatorFile = 'model/vendor/vendorDetailsSearchTableCreator.php';

// File that creates the sale details search table
saleDetailsSearchTableCreatorFile = 'model/sale/saleDetailsSearchTableCreator.php';



// File that creates the purchase reports search table
purchaseReportsSearchTableCreatorFile = 'model/purchase/purchaseReportsSearchTableCreator.php';

// File that creates the customer reports search table
customerReportsSearchTableCreatorFile = 'model/customer/customerReportsSearchTableCreator.php';

// File that creates the item reports search table
itemReportsSearchTableCreatorFile = 'model/item/itemReportsSearchTableCreator.php';

// File that creates the vendor reports search table
vendorReportsSearchTableCreatorFile = 'model/vendor/vendorReportsSearchTableCreator.php';

// File that creates the sale reports search table
saleReportsSearchTableCreatorFile = 'model/sale/saleReportsSearchTableCreator.php';



// File that returns the last inserted vendorID
vendorLastInsertedIDFile = 'model/vendor/populateLastVendorID.php';

// File that returns the last inserted customerID
customerLastInsertedIDFile = 'model/customer/populateLastCustomerID.php';

// File that returns the last inserted purchase transaction ID
purchaseLastInsertedIDFile = 'model/purchase/populateLastPurchaseTransactionID.php';

// File that returns the last inserted sale transaction ID
saleLastInsertedIDFile = 'model/sale/populateLastTransactionID.php';

// File that returns the last inserted productID for item details tab
itemLastInsertedIDFile = 'model/item/populateLastProductID.php';



// File that returns purchaseIDs
showPurchaseIDSuggestionsFile = 'model/purchase/showPurchaseIDs.php';

// File that returns saleIDs
showSaleIDSuggestionsFile = 'model/sale/showSaleIDs.php';

// File that returns vendorIDs
showVendorIDSuggestionsFile = 'model/vendor/showVendorIDs.php';

// File that returns customerIDs
showCustomerIDSuggestionsFile = 'model/customer/showCustomerIDs.php';

// File that returns customerIDs for sale tab
showCustomerIDSuggestionsForSaleTabFile = 'model/customer/showCustomerIDsForSaleTab.php';

// File that returns customerIDs for the credit tab
showCustomerIDSuggestionsForCreditTabFile = 'model/customer/showCustomerIDsForCreditTab.php';



// File that returns itemNumbers
showItemNumberSuggestionsFile = 'model/item/showItemNumber.php';

// File that returns itemNumbers in image tab
showItemNumberSuggestionsForImageTabFile = 'model/item/showItemNumberForImageTab.php';

// File that returns itemNumbers for purchase tab
showItemNumberForPurchaseTabFile = 'model/item/showItemNumberForPurchaseTab.php';

// File that returns itemNumbers for sale tab
showItemNumberForSaleTabFile = 'model/item/showItemNumberForSaleTab.php';

// File that returns itemNames
showItemNamesFile = 'model/item/showItemNames.php';



// File that returns stock 
getItemStockFile = 'model/item/getItemStock.php';

// File that returns item name
getItemNameFile = 'model/item/getItemName.php';

// File that updates an image
updateImageFile = 'model/image/updateImage.php';

// File that deletes an image
deleteImageFile = 'model/image/deleteImage.php';



// File that creates the filtered purchase report table
purchaseFilteredReportCreatorFile = 'model/purchase/purchaseFilteredReportTableCreator.php';

// File that creates the filtered sale report table
saleFilteredReportCreatorFile = 'model/sale/saleFilteredReportTableCreator.php';


function formatCurrency(value) {
	var amount = Number(value);
	if (isNaN(amount)) {
		amount = 0;
	}
	return '\u20a6' + amount.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateDashboardMovementTable(movements) {
	var tableSelector = '#dashboardRecentMovementsTable';
	if (!Array.isArray(movements)) {
		movements = [];
	}

	if ($.fn.DataTable.isDataTable(tableSelector)) {
		var movementTable = $(tableSelector).DataTable();
		var rows = [];
		for (var i = 0; i < movements.length; i++) {
			var movement = movements[i] || {};
			var itemName = movement.itemName || '';
			var itemNumber = movement.itemNumber || '';
			rows.push([
				movement.movementDate || '',
				itemName + ' (' + itemNumber + ')',
				movement.quantity || 0,
				movement.direction || '',
				movement.referenceName || '',
				movement.reason || ''
			]);
		}

		movementTable.clear();
		if (rows.length > 0) {
			movementTable.rows.add(rows);
		}
		movementTable.draw(false);
	}
}

function activatePillFromHash() {
	var hash = window.location.hash;
	if (!hash || hash.indexOf('#v-pills-') !== 0) {
		return;
	}

	var $trigger = $('a[data-toggle="pill"][href="' + hash + '"]');
	if ($trigger.length > 0) {
		$trigger.tab('show');
	}
}



$(document).ready(function () {
	activatePillFromHash();
	$(window).on('hashchange', activatePillFromHash);
	$('a[data-toggle="pill"]').on('shown.bs.tab', function (event) {
		var targetHash = $(event.target).attr('href');
		if (targetHash && targetHash.indexOf('#v-pills-') === 0 && window.location.hash !== targetHash) {
			if (window.history && typeof window.history.replaceState === 'function') {
				window.history.replaceState(null, '', targetHash);
			} else {
				window.location.hash = targetHash;
			}
		}
	});

	// Style the dropdown boxes. You need to explicitly set the width 
	// in order to fix the dropdown box not visible issue when tab is hidden
	$('.chosenSelect').chosen({ width: "95%" });

	// Initiate tooltips
	$('.invTooltip').tooltip();

	$(document).on('click', '.transaction-id-copy', function () {
		var transactionID = String($(this).data('transaction-id') || '').trim();
		if (transactionID === '') {
			showToastMessage('Transaction ID is unavailable to copy.', '', 'warning');
			return;
		}

		function showCopyResult(wasCopied) {
			showToastMessage(
				wasCopied ? 'Transaction ID copied: ' + transactionID : 'Unable to copy the transaction ID. Please copy it manually.',
				'',
				wasCopied ? 'success' : 'danger'
			);
		}

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(transactionID).then(function () {
				showCopyResult(true);
			}).catch(function () {
				showCopyResult(false);
			});
			return;
		}

		var copyInput = $('<textarea>').val(transactionID).css({ position: 'fixed', opacity: 0 }).appendTo('body').select();
		var wasCopied = document.execCommand('copy');
		copyInput.remove();
		showCopyResult(wasCopied);
	});

	// Refresh dashboard cards from the database
	$('#refreshDashboardBtn').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true).text('Refreshing...');

		$.ajax({
			url: 'model/dashboard/getDashboardSummary.php',
			method: 'GET',
			cache: false,
			data: { _: Date.now() },
			success: function (data) {
				var result = data;
				if (typeof data === 'string') {
					try {
						result = $.parseJSON(data);
					} catch (e) {
						showToastMessage('Unable to refresh dashboard right now.', 'saleDetailsMessage');
						return;
					}
				}

				if (!result || typeof result !== 'object') {
					showToastMessage('Invalid dashboard response received.', 'saleDetailsMessage');
					return;
				}

				if (result.error) {
					showToastMessage(result.error, 'saleDetailsMessage');
					return;
				}

				$('#dashboardCurrentStockValue').text(formatCurrency(Number(result.currentStockValue || 0)));
				$('#dashboardTotalSales').text(formatCurrency(Number(result.totalSales || 0)));
				$('#dashboardTotalPurchases').text(formatCurrency(Number(result.totalPurchases || 0)));
				$('#dashboardTotalCredits').text(formatCurrency(Number(result.totalCredits || 0)));
				updateDashboardMovementTable(result.movements);
				showToastMessage('Dashboard refreshed successfully.', 'saleDetailsMessage');
			},
			error: function () {
				showToastMessage('Failed to refresh dashboard. Please try again.', 'saleDetailsMessage');
			},
			complete: function () {
				$btn.prop('disabled', false).text('Refresh');
			}
		});
	});

	// Enable pagination and export actions on dashboard movement tables
	if ($.fn.DataTable.isDataTable('#dashboardRecentMovementsTable') === false) {
		$('#dashboardRecentMovementsTable').DataTable({
			dom: 'lBfrtip',
			pageLength: 15,
			lengthMenu: [[15, 25, 50, 100, -1], [15, 25, 50, 100, 'All']],
			order: [[0, 'desc']],
			buttons: [
				'copy',
				{ extend: 'csv', title: 'Stock Movements' },
				{ extend: 'excel', title: 'Stock Movements' },
				{ extend: 'pdf', orientation: 'landscape', pageSize: 'LEGAL', title: 'Stock Movements' },
				{ extend: 'print', title: 'Stock Movements' }
			]
		});
	}

	// Enable pagination and export actions on low stock alerts table
	if ($('#lowStockAlertsTable').length && $.fn.DataTable.isDataTable('#lowStockAlertsTable') === false) {
		$('#lowStockAlertsTable').DataTable({
			dom: 'lBfrtip',
			pageLength: 15,
			lengthMenu: [[15, 25, 50, 100, -1], [15, 25, 50, 100, 'All']],
			order: [[2, 'asc']],
			buttons: [
				'copy',
				{ extend: 'csv', title: 'Low Stock Alerts' },
				{ extend: 'excel', title: 'Low Stock Alerts' },
				{ extend: 'pdf', orientation: 'landscape', pageSize: 'LEGAL', title: 'Low Stock Alerts' },
				{ extend: 'print', title: 'Low Stock Alerts' }
			]
		});
	}

	// Listen to customer add button
	$('#addCustomer').on('click', function () {
		addCustomer();
	});

	// Listen to vendor add button
	$('#addVendor').on('click', function () {
		addVendor();
	});

	// Listen to item add button
	$('#addItem').on('click', function () {
		addItem();
	});

	// Listen to purchase add button
	$('#addPurchase').on('click', function () {
		addPurchase();
	});

	$('#addPurchaseItemRowButton').on('click', function () {
		addPurchaseItemRow();
	});

	$('#purchaseDetailsForm').on('reset', function () {
		setTimeout(calculatePurchaseItemsGrandTotal, 0);
	});

	// Listen to sale add button
	$('#addSaleButton').on('click', function () {
		addSale();
	});

	$('#addSaleItemRowButton').on('click', function () {
		addSaleItemRow();
	});

	$('#saleDetailsForm').on('reset', function () {
		setTimeout(calculateSaleItemsGrandTotal, 0);
	});

	// Listen to update button in item details tab
	$('#updateItemDetailsButton').on('click', function () {
		updateItem();
	});

	// Listen to update button in customer details tab
	$('#updateCustomerDetailsButton').on('click', function () {
		updateCustomer();
	});

	// Listen to update button in vendor details tab
	$('#updateVendorDetailsButton').on('click', function () {
		updateVendor();
	});

	// Listen to update button in purchase details tab
	$('#updatePurchaseDetailsButton').on('click', function () {
		updatePurchase();
	});

	$('#loadPurchaseTransactionButton').on('click', function () {
		getPurchaseDetailsToPopulate();
	});

	// Listen to update button in sale details tab
	$('#updateSaleDetailsButton').on('click', function () {
		updateSale();
	});

	$('#loadSaleTransactionButton').on('click', function () {
		getSaleDetailsToPopulate();
	});

	// Credit book actions
	$('#viewCreditBookButton').on('click', function () {
		viewCreditBook();
	});

	$('#recordPaymentButton').on('click', function () {
		recordCustomerPayment();
	});

	$('#printReceiptButton').on('click', function () {
		printCustomerReceipt();
	});

	$('#printSaleReceiptButton').on('click', function () {
		printSaleReceipt();
	});

	// Listen to delete button in item details tab
	$('#deleteItem').on('click', function () {
		// Confirm before deleting
		bootbox.confirm('Are you sure you want to delete?', function (result) {
			if (result) {
				deleteItem();
			}
		});
	});

	// Listen to delete button in customer details tab
	$('#deleteCustomerButton').on('click', function () {
		// Confirm before deleting
		bootbox.confirm('Are you sure you want to delete?', function (result) {
			if (result) {
				deleteCustomer();
			}
		});
	});

	// Listen to delete button in vendor details tab
	$('#deleteVendorButton').on('click', function () {
		// Confirm before deleting
		bootbox.confirm('Are you sure you want to delete?', function (result) {
			if (result) {
				deleteVendor();
			}
		});
	});

	// Listen to item name text box in item details tab
	$('#itemDetailsItemName').keyup(function () {
		showSuggestions('itemDetailsItemName', showItemNamesFile, 'itemDetailsItemNameSuggestionsDiv');
	});

	// Remove the item names suggestions dropdown in the item details tab
	// when user selects an item from it
	$(document).on('click', '#itemDetailsItemNamesSuggestionsList li', function () {
		$('#itemDetailsItemName').val($(this).text());
		$('#itemDetailsItemNamesSuggestionsList').fadeOut();
	});

	// Listen to item number text box in item details tab
	$('#itemDetailsItemNumber').keyup(function () {
		showSuggestions('itemDetailsItemNumber', showItemNumberSuggestionsFile, 'itemDetailsItemNumberSuggestionsDiv');
	});

	// Remove the item numbers suggestions dropdown in the item details tab
	// when user selects an item from it
	$(document).on('click', '#itemDetailsItemNumberSuggestionsList li', function () {
		$('#itemDetailsItemNumber').val($(this).text());
		$('#itemDetailsItemNumberSuggestionsList').fadeOut();
		getItemDetailsToPopulate();
	});


	// Listen to item number text box in sale details tab
	$('#saleDetailsItemNumber').keyup(function () {
		showSuggestions('saleDetailsItemNumber', showItemNumberForSaleTabFile, 'saleDetailsItemNumberSuggestionsDiv');
	});

	// Remove the item numbers suggestions dropdown in the sale details tab
	// when user selects an item from it
	$(document).on('click', '#saleDetailsItemNumberSuggestionsList li', function () {
		$('#saleDetailsItemNumber').val($(this).text());
		$('#saleDetailsItemNumberSuggestionsList').fadeOut();
		getItemDetailsToPopulateForSaleTab();
	});


	// Listen to item number text box in item image tab
	$('#itemImageItemNumber').keyup(function () {
		showSuggestions('itemImageItemNumber', showItemNumberSuggestionsForImageTabFile, 'itemImageItemNumberSuggestionsDiv');
	});

	// Remove the item numbers suggestions dropdown in the item image tab
	// when user selects an item from it
	$(document).on('click', '#itemImageItemNumberSuggestionsList li', function () {
		$('#itemImageItemNumber').val($(this).text());
		$('#itemImageItemNumberSuggestionsList').fadeOut();
		getItemName('itemImageItemNumber', getItemNameFile, 'itemImageItemName');
	});

	// Clear the image from item tab when Clear button is clicked
	$('#itemClear').on('click', function () {
		$('#imageContainer').empty();
	});

	// Clear the image from sale tab when Clear button is clicked
	$('#saleClear').on('click', function () {
		$('#saleDetailsImageContainer').empty();
	});

	// Refresh the purchase report datatable in the purchase report tab when Clear button is clicked
	$('#purchaseFilterClear').on('click', function () {
		reportsPurchaseTableCreator('purchaseReportsTableDiv', purchaseReportsSearchTableCreatorFile, 'purchaseReportsTable');
	});

	// Refresh the sale report datatable in the sale report tab when Clear button is clicked
	$('#saleFilterClear').on('click', function () {
		reportsSaleTableCreator('saleReportsTableDiv', saleReportsSearchTableCreatorFile, 'saleReportsTable');
	});


	// Listen to item number text box in purchase details tab
	$('#purchaseDetailsItemNumber').keyup(function () {
		showSuggestions('purchaseDetailsItemNumber', showItemNumberForPurchaseTabFile, 'purchaseDetailsItemNumberSuggestionsDiv');
	});

	// remove the item numbers suggestions dropdown in the purchase details tab
	// when user selects an item from it
	$(document).on('click', '#purchaseDetailsItemNumberSuggestionsList li', function () {
		$('#purchaseDetailsItemNumber').val($(this).text());
		$('#purchaseDetailsItemNumberSuggestionsList').fadeOut();

		// Display the item name for the selected item number
		getItemName('purchaseDetailsItemNumber', getItemNameFile, 'purchaseDetailsItemName');

		// Display the current stock for the selected item number
		getItemStockToPopulate('purchaseDetailsItemNumber', getItemStockFile, 'purchaseDetailsCurrentStock');
	});

	// Listen to CustomerID text box in customer details tab
	$('#customerDetailsCustomerID').keyup(function () {
		showSuggestions('customerDetailsCustomerID', showCustomerIDSuggestionsFile, 'customerDetailsCustomerIDSuggestionsDiv');
	});

	// Remove the CustomerID suggestions dropdown in the customer details tab
	// when user selects an item from it
	$(document).on('click', '#customerDetailsCustomerIDSuggestionsList li', function () {
		$('#customerDetailsCustomerID').val($(this).text());
		$('#customerDetailsCustomerIDSuggestionsList').fadeOut();
		getCustomerDetailsToPopulate();
	});


	// Listen to CustomerID text box in sale details tab
	$('#saleDetailsCustomerID').keyup(function () {
		showSuggestions('saleDetailsCustomerID', showCustomerIDSuggestionsForSaleTabFile, 'saleDetailsCustomerIDSuggestionsDiv');
	});

	// Remove the CustomerID suggestions dropdown in the sale details tab
	// when user selects an item from it
	$(document).on('click', '#saleDetailsCustomerIDSuggestionsList li', function () {
		var customerID = $(this).data('customer-id') || $(this).text();
		$('#saleDetailsCustomerID').val(customerID);
		$('#saleDetailsCustomerIDSuggestionsList').fadeOut();
		getCustomerDetailsToPopulateSaleTab();
	});

	// Listen to CustomerID text box in the credit book tab
	$('#creditCustomerID').keyup(function () {
		showSuggestions('creditCustomerID', showCustomerIDSuggestionsForCreditTabFile, 'creditCustomerIDSuggestionsDiv');
	});

	// Remove the CustomerID suggestions dropdown in the credit book tab
	// when user selects a customer from it
	$(document).on('click', '#creditCustomerIDSuggestionsList li', function () {
		var customerID = $(this).data('customer-id') || $(this).text();
		$('#creditCustomerID').val(customerID);
		$('#creditCustomerIDSuggestionsList').fadeOut();
		viewCreditBook();
	});

	$('#creditCustomerID').on('change blur', function () {
		if (/^\d+$/.test($.trim($(this).val()))) {
			viewCreditBook();
		}
	});

	$('#creditTransactionID').keyup(function () {
		showSuggestions('creditTransactionID', showSaleIDSuggestionsFile, 'creditTransactionIDSuggestionsDiv', {
			suggestionsListID: 'creditTransactionIDSuggestionsList',
			customerID: $('#creditCustomerID').val()
		});
	});

	$(document).on('click', '#creditTransactionIDSuggestionsList li', function () {
		$('#creditTransactionID').val($(this).text());
		$('#creditTransactionIDSuggestionsList').fadeOut();
	});


	// Listen to VendorID text box in vendor details tab
	$('#vendorDetailsVendorID').keyup(function () {
		showSuggestions('vendorDetailsVendorID', showVendorIDSuggestionsFile, 'vendorDetailsVendorIDSuggestionsDiv');
	});

	// Remove the VendorID suggestions dropdown in the vendor details tab
	// when user selects an item from it
	$(document).on('click', '#vendorDetailsVendorIDSuggestionsList li', function () {
		$('#vendorDetailsVendorID').val($(this).text());
		$('#vendorDetailsVendorIDSuggestionsList').fadeOut();
		getVendorDetailsToPopulate();
	});


	// Listen to PurchaseID text box in purchase details tab
	$('#purchaseDetailsPurchaseID').keyup(function () {
		showSuggestions('purchaseDetailsPurchaseID', showPurchaseIDSuggestionsFile, 'purchaseDetailsPurchaseIDSuggestionsDiv');
	});

	// Remove the PurchaseID suggestions dropdown in the customer details tab
	// when user selects an item from it
	$(document).on('click', '#purchaseDetailsPurchaseIDSuggestionsList li', function () {
		$('#purchaseDetailsPurchaseID').val($(this).text());
		$('#purchaseDetailsPurchaseIDSuggestionsList').fadeOut();
		getPurchaseDetailsToPopulate();
	});


	// Listen to saleID text box in sale details tab
	$('#saleDetailsSaleID').keyup(function () {
		showSuggestions('saleDetailsSaleID', showSaleIDSuggestionsFile, 'saleDetailsSaleIDSuggestionsDiv');
	});

	// Remove the SaleID suggestions dropdown in the sale details tab
	// when user selects an item from it
	$(document).on('click', '#saleDetailsSaleIDSuggestionsList li', function () {
		$('#saleDetailsSaleID').val($(this).text());
		$('#saleDetailsSaleIDSuggestionsList').fadeOut();
		getSaleDetailsToPopulate();
	});


	// Listen to image update button
	$('#updateImageButton').on('click', function () {
		processImage('imageForm', updateImageFile, 'itemImageMessage');
	});

	// Listen to image delete button
	$('#deleteImageButton').on('click', function () {
		processImage('imageForm', deleteImageFile, 'itemImageMessage');
	});

	// Initiate datepickers
	$('.datepicker').datepicker({
		format: 'yyyy-mm-dd',
		autoclose: true,
		todayHighlight: true,
		todayBtn: 'linked',
		orientation: 'bottom left'
	});

	// Calculate Total in purchase tab
	$('#purchaseDetailsQuantity, #purchaseDetailsUnitPrice').change(function () {
		calculateTotalInPurchaseTab();
	});

	// Calculate Total in sale tab
	$('#saleDetailsDiscount, #saleDetailsQuantity, #saleDetailsUnitPrice').change(function () {
		calculateTotalInSaleTab();
	});

	// Close any suggestions lists from the page when a user clicks on the page
	$(document).on('click', function () {
		$('.suggestionsList').fadeOut();
	});

	// Load searchable datatables for customer, purchase, item, vendor, sale
	searchTableCreator('itemDetailsTableDiv', itemDetailsSearchTableCreatorFile, 'itemDetailsTable');
	searchTableCreator('purchaseDetailsTableDiv', purchaseDetailsSearchTableCreatorFile, 'purchaseDetailsTable');
	searchTableCreator('customerDetailsTableDiv', customerDetailsSearchTableCreatorFile, 'customerDetailsTable');
	searchTableCreator('saleDetailsTableDiv', saleDetailsSearchTableCreatorFile, 'saleDetailsTable');
	searchTableCreator('vendorDetailsTableDiv', vendorDetailsSearchTableCreatorFile, 'vendorDetailsTable');

	// Load searchable datatables for customer, purchase, item, vendor, sale reports
	reportsTableCreator('itemReportsTableDiv', itemReportsSearchTableCreatorFile, 'itemReportsTable');
	reportsPurchaseTableCreator('purchaseReportsTableDiv', purchaseReportsSearchTableCreatorFile, 'purchaseReportsTable');
	reportsTableCreator('customerReportsTableDiv', customerReportsSearchTableCreatorFile, 'customerReportsTable');
	reportsSaleTableCreator('saleReportsTableDiv', saleReportsSearchTableCreatorFile, 'saleReportsTable');
	reportsTableCreator('vendorReportsTableDiv', vendorReportsSearchTableCreatorFile, 'vendorReportsTable');

	// Initiate popovers
	$(document).on('mouseover', '.itemDetailsHover', function () {
		// Create item details popover boxes
		$('.itemDetailsHover').popover({
			container: 'body',
			title: 'Item Details',
			trigger: 'hover',
			html: true,
			placement: 'right',
			content: fetchData
		});
	});

	// Listen to refresh buttons
	$('#searchTablesRefresh, #reportsTablesRefresh').on('click', function () {
		searchTableCreator('itemDetailsTableDiv', itemDetailsSearchTableCreatorFile, 'itemDetailsTable');
		searchTableCreator('purchaseDetailsTableDiv', purchaseDetailsSearchTableCreatorFile, 'purchaseDetailsTable');
		searchTableCreator('customerDetailsTableDiv', customerDetailsSearchTableCreatorFile, 'customerDetailsTable');
		searchTableCreator('vendorDetailsTableDiv', vendorDetailsSearchTableCreatorFile, 'vendorDetailsTable');
		searchTableCreator('saleDetailsTableDiv', saleDetailsSearchTableCreatorFile, 'saleDetailsTable');

		reportsTableCreator('itemReportsTableDiv', itemReportsSearchTableCreatorFile, 'itemReportsTable');
		reportsPurchaseTableCreator('purchaseReportsTableDiv', purchaseReportsSearchTableCreatorFile, 'purchaseReportsTable');
		reportsTableCreator('customerReportsTableDiv', customerReportsSearchTableCreatorFile, 'customerReportsTable');
		reportsTableCreator('vendorReportsTableDiv', vendorReportsSearchTableCreatorFile, 'vendorReportsTable');
		reportsSaleTableCreator('saleReportsTableDiv', saleReportsSearchTableCreatorFile, 'saleReportsTable');
	});


	// Listen to purchase report show button
	$('#showPurchaseReport').on('click', function () {
		filteredPurchaseReportTableCreator('purchaseReportStartDate', 'purchaseReportEndDate', purchaseFilteredReportCreatorFile, 'purchaseReportsTableDiv', 'purchaseFilteredReportsTable');
	});

	// Listen to sale report show button
	$('#showSaleReport').on('click', function () {
		filteredSaleReportTableCreator('saleReportStartDate', 'saleReportEndDate', saleFilteredReportCreatorFile, 'saleReportsTableDiv', 'saleFilteredReportsTable');
	});

	// Ensure sales and purchase tabs always have one editable row after fresh load.
	if ($('#purchaseItemsContainer .purchase-item-row').length === 0) {
		addPurchaseItemRow();
	}
	if ($('#saleItemsContainer .sale-item-row').length === 0) {
		addSaleItemRow();
	}

	$('form').on('reset', function () {
		var $form = $(this);
		setTimeout(function () {
			if ($form.find('#purchaseItemsContainer').length) {
				$('#purchaseItemsContainer').empty();
				addPurchaseItemRow();
			}
			if ($form.find('#saleItemsContainer').length) {
				$('#saleItemsContainer').empty();
				addSaleItemRow();
			}
		}, 0);
	});

});


// Function to fetch data to show in popovers
function fetchData() {
	var fetch_data = '';
	var element = $(this);
	var id = element.attr('id');

	$.ajax({
		url: 'model/item/getItemDetailsForPopover.php',
		method: 'POST',
		async: false,
		data: { id: id },
		success: function (data) {
			fetch_data = data;
		}
	});
	return fetch_data;
}


function showToastMessage(message, messageDivID, fallbackType) {
	if (message === undefined || message === null || message === '') {
		return;
	}

	if (messageDivID) {
		$('#' + messageDivID).empty();
	}

	var messageText = '';
	if (typeof message === 'object' && message !== null) {
		messageText = message.message || message.error || JSON.stringify(message);
	} else {
		messageText = $('<div></div>').html(message).text().trim();
	}
	if (messageText === '') {
		messageText = 'Request completed.';
	}

	var type = fallbackType || 'info';
	var lowerMessage = messageText.toLowerCase();

	var rawMessageText = typeof message === 'string' ? message : '';

	if (rawMessageText.indexOf('alert-danger') !== -1 || lowerMessage.indexOf('error') !== -1 || lowerMessage.indexOf('invalid') !== -1 || lowerMessage.indexOf('fail') !== -1 || lowerMessage.indexOf('does not exist') !== -1) {
		type = 'danger';
	} else if (rawMessageText.indexOf('alert-success') !== -1 || lowerMessage.indexOf('successfully') !== -1 || lowerMessage.indexOf('success') !== -1 || lowerMessage.indexOf('added') !== -1 || lowerMessage.indexOf('updated') !== -1 || lowerMessage.indexOf('deleted') !== -1 || lowerMessage.indexOf('uploaded') !== -1) {
		type = 'success';
	} else if (rawMessageText.indexOf('alert-warning') !== -1 || lowerMessage.indexOf('warning') !== -1) {
		type = 'warning';
	}

	var titleMap = {
		success: 'Success',
		danger: 'Error',
		warning: 'Warning',
		info: 'Notice'
	};

	var toastHost = $('#toastContainer');
	if (toastHost.length === 0) {
		$('body').append('<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>');
		toastHost = $('#toastContainer');
	}

	var toast = $('<div class="custom-toast custom-toast-' + type + '" role="alert" aria-live="assertive" aria-atomic="true"></div>');
	toast.append('<div class="custom-toast-title">' + titleMap[type] + '</div>');
	toast.append('<div class="custom-toast-body">' + messageText + '</div>');
	toastHost.append(toast);

	setTimeout(function () {
		toast.addClass('show');
	}, 10);

	setTimeout(function () {
		toast.removeClass('show');
		setTimeout(function () {
			toast.remove();
		}, 300);
	}, 15000);
}


// Function to call the script that process imageURL in DB
function processImage(imageFormID, scriptPath, messageDivID) {
	var form = $('#' + imageFormID)[0];
	var formData = new FormData(form);
	$.ajax({
		url: scriptPath,
		method: 'POST',
		data: formData,
		contentType: false,
		processData: false,
		success: function (data) {
			$('#' + messageDivID).html(data);
		}
	});
}

// Function to create searchable datatables for customer, item, purchase, sale
function searchTableCreator(tableContainerDiv, tableCreatorFileUrl, table) {
	var tableContainerDivID = '#' + tableContainerDiv;
	var tableID = '#' + table;
	$(tableContainerDivID).load(tableCreatorFileUrl, function () {
		// Initiate the Datatable plugin once the table is added to the DOM
		$(tableID).DataTable();
	});
}


// Function to create reports datatables for customer, item, purchase, sale
function reportsTableCreator(tableContainerDiv, tableCreatorFileUrl, table) {
	var tableContainerDivID = '#' + tableContainerDiv;
	var tableID = '#' + table;
	$(tableContainerDivID).load(tableCreatorFileUrl, function () {
		// Initiate the Datatable plugin once the table is added to the DOM
		$(tableID).DataTable({
			dom: 'lBfrtip',
			//dom: 'lfBrtip',
			//dom: 'Bfrtip',
			buttons: [
				'copy',
				'csv', 'excel',
				{ extend: 'pdf', orientation: 'landscape', pageSize: 'LEGAL' },
				'print'
			]
		});
	});
}


// Function to create reports datatables for purchase
function reportsPurchaseTableCreator(tableContainerDiv, tableCreatorFileUrl, table) {
	var tableContainerDivID = '#' + tableContainerDiv;
	var tableID = '#' + table;
	$(tableContainerDivID).load(tableCreatorFileUrl, function () {
		// Initiate the Datatable plugin once the table is added to the DOM
		$(tableID).DataTable({
			dom: 'lBfrtip',
			buttons: [
				'copy',
				{ extend: 'csv', footer: true, title: 'Purchase Report' },
				{ extend: 'excel', footer: true, title: 'Purchase Report' },
				{ extend: 'pdf', footer: true, orientation: 'landscape', pageSize: 'LEGAL', title: 'Purchase Report' },
				{ extend: 'print', footer: true, title: 'Purchase Report' },
			],
			"footerCallback": function (row, data, start, end, display) {
				var api = this.api(), data;

				// Remove the formatting to get integer data for summation
				var intVal = function (i) {
					return typeof i === 'string' ?
						i.replace(/[\$,]/g, '') * 1 :
						typeof i === 'number' ?
							i : 0;
				};

				// Quantity total over all pages
				quantityTotal = api
					.column(4)
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Quantity for current page
				quantityFilteredTotal = api
					.column(4, { page: 'current' })
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Unit price total over all pages
				unitPriceTotal = api
					.column(8)
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Unit price for current page
				unitPriceFilteredTotal = api
					.column(8, { page: 'current' })
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Full price total over all pages
				fullPriceTotal = api
					.column(9)
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Full price for current page
				fullPriceFilteredTotal = api
					.column(9, { page: 'current' })
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Update footer columns
				$(api.column(4).footer()).html(quantityFilteredTotal + ' (' + quantityTotal + ' total)');
				$(api.column(8).footer()).html(unitPriceFilteredTotal + ' (' + unitPriceTotal + ' total)');
				$(api.column(9).footer()).html(fullPriceFilteredTotal + ' (' + fullPriceTotal + ' total)');
			}
		});
	});
}


// Function to create reports datatables for sale
function reportsSaleTableCreator(tableContainerDiv, tableCreatorFileUrl, table) {
	var tableContainerDivID = '#' + tableContainerDiv;
	var tableID = '#' + table;
	$(tableContainerDivID).load(tableCreatorFileUrl, function () {
		// Initiate the Datatable plugin once the table is added to the DOM
		$(tableID).DataTable({
			dom: 'lBfrtip',
			buttons: [
				'copy',
				{ extend: 'csv', footer: true, title: 'Sale Report' },
				{ extend: 'excel', footer: true, title: 'Sale Report' },
				{ extend: 'pdf', footer: true, orientation: 'landscape', pageSize: 'LEGAL', title: 'Sale Report' },
				{ extend: 'print', footer: true, title: 'Sale Report' },
			],
			"footerCallback": function (row, data, start, end, display) {
				var api = this.api(), data;

				// Remove the formatting to get integer data for summation
				var intVal = function (i) {
					return typeof i === 'string' ?
						i.replace(/[\$,]/g, '') * 1 :
						typeof i === 'number' ?
							i : 0;
				};

				// Quantity Total over all pages
				quantityTotal = api
					.column(6)
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Quantity Total over this page
				quantityFilteredTotal = api
					.column(6, { page: 'current' })
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Unit price Total over all pages
				unitPriceTotal = api
					.column(9)
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Unit price total over current page
				unitPriceFilteredTotal = api
					.column(9, { page: 'current' })
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Full price Total over all pages
				fullPriceTotal = api
					.column(10)
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Full price total over current page
				fullPriceFilteredTotal = api
					.column(10, { page: 'current' })
					.data()
					.reduce(function (a, b) {
						return intVal(a) + intVal(b);
					}, 0);

				// Update footer columns
				$(api.column(6).footer()).html(quantityFilteredTotal + ' (' + quantityTotal + ' total)');
				$(api.column(9).footer()).html(unitPriceFilteredTotal + ' (' + unitPriceTotal + ' total)');
				$(api.column(10).footer()).html(fullPriceFilteredTotal + ' (' + fullPriceTotal + ' total)');
			}
		});
	});
}


// Function to create filtered datatable for sale details with total values
function filteredSaleReportTableCreator(startDate, endDate, scriptPath, tableDIV, tableID) {
	var startDate = $('#' + startDate).val();
	var endDate = $('#' + endDate).val();

	$.ajax({
		url: scriptPath,
		method: 'POST',
		data: {
			startDate: startDate,
			endDate: endDate,
		},
		success: function (data) {
			$('#' + tableDIV).empty();
			$('#' + tableDIV).html(data);
		},
		complete: function () {
			// Initiate the Datatable plugin once the table is added to the DOM
			$('#' + tableID).DataTable({
				dom: 'lBfrtip',
				buttons: [
					'copy',
					{ extend: 'csv', footer: true, title: 'Sale Report' },
					{ extend: 'excel', footer: true, title: 'Sale Report' },
					{ extend: 'pdf', footer: true, orientation: 'landscape', pageSize: 'LEGAL', title: 'Sale Report' },
					{ extend: 'print', footer: true, title: 'Sale Report' },
				],
				"footerCallback": function (row, data, start, end, display) {
					var api = this.api(), data;

					// Remove the formatting to get integer data for summation
					var intVal = function (i) {
						return typeof i === 'string' ?
							i.replace(/[\$,]/g, '') * 1 :
							typeof i === 'number' ?
								i : 0;
					};

					// Total over all pages
					quantityTotal = api
						.column(4)
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Total over this page
					quantityFilteredTotal = api
						.column(4, { page: 'current' })
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Total over all pages
					unitPriceTotal = api
						.column(8)
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Quantity total
					unitPriceFilteredTotal = api
						.column(8, { page: 'current' })
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Full total over all pages
					fullPriceTotal = api
						.column(9)
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Full total over current page
					fullPriceFilteredTotal = api
						.column(9, { page: 'current' })
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Update footer columns
					$(api.column(4).footer()).html(quantityFilteredTotal + ' (' + quantityTotal + ' total)');
					$(api.column(8).footer()).html(unitPriceFilteredTotal + ' (' + unitPriceTotal + ' total)');
					$(api.column(9).footer()).html(fullPriceFilteredTotal + ' (' + fullPriceTotal + ' total)');
				}
			});
		}
	});
}


// Function to create filtered datatable for purchase details with total values
function filteredPurchaseReportTableCreator(startDate, endDate, scriptPath, tableDIV, tableID) {
	var startDate = $('#' + startDate).val();
	var endDate = $('#' + endDate).val();

	$.ajax({
		url: scriptPath,
		method: 'POST',
		data: {
			startDate: startDate,
			endDate: endDate,
		},
		success: function (data) {
			$('#' + tableDIV).empty();
			$('#' + tableDIV).html(data);
		},
		complete: function () {
			// Initiate the Datatable plugin once the table is added to the DOM
			$('#' + tableID).DataTable({
				dom: 'lBfrtip',
				buttons: [
					'copy',
					{ extend: 'csv', footer: true, title: 'Purchase Report' },
					{ extend: 'excel', footer: true, title: 'Purchase Report' },
					{ extend: 'pdf', footer: true, orientation: 'landscape', pageSize: 'LEGAL', title: 'Purchase Report' },
					{ extend: 'print', footer: true, title: 'Purchase Report' }
				],
				"footerCallback": function (row, data, start, end, display) {
					var api = this.api(), data;

					// Remove the formatting to get integer data for summation
					var intVal = function (i) {
						return typeof i === 'string' ?
							i.replace(/[\$,]/g, '') * 1 :
							typeof i === 'number' ?
								i : 0;
					};

					// Quantity total over all pages
					quantityTotal = api
						.column(6)
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Quantity for current page
					quantityFilteredTotal = api
						.column(6, { page: 'current' })
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Unit price total over all pages
					unitPriceTotal = api
						.column(8)
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Unit price for current page
					unitPriceFilteredTotal = api
						.column(8, { page: 'current' })
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Full price total over all pages
					fullPriceTotal = api
						.column(9)
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Full price for current page
					fullPriceFilteredTotal = api
						.column(9, { page: 'current' })
						.data()
						.reduce(function (a, b) {
							return intVal(a) + intVal(b);
						}, 0);

					// Update footer columns
					$(api.column(6).footer()).html(quantityFilteredTotal + ' (' + quantityTotal + ' total)');
					$(api.column(8).footer()).html(unitPriceFilteredTotal + ' (' + unitPriceTotal + ' total)');
					$(api.column(9).footer()).html(fullPriceFilteredTotal + ' (' + fullPriceTotal + ' total)');
				}
			});
		}
	});
}


// Calculate Total Purchase value in purchase details tab
function calculateTotalInPurchaseTab() {
	var quantityPT = $('#purchaseDetailsQuantity').val();
	var unitPricePT = $('#purchaseDetailsUnitPrice').val();
	$('#purchaseDetailsTotal').val(Number(quantityPT) * Number(unitPricePT));
}


// Calculate Total sale value in sale details tab
function calculateTotalInSaleTab() {
	var quantityST = $('#saleDetailsQuantity').val();
	var unitPriceST = $('#saleDetailsUnitPrice').val();
	var discountST = $('#saleDetailsDiscount').val();
	$('#saleDetailsTotal').val(Number(unitPriceST) * ((100 - Number(discountST)) / 100) * Number(quantityST));
}


// Function to call the insertCustomer.php script to insert customer data to db
function addCustomer() {
	var customerDetailsCustomerFullName = $('#customerDetailsCustomerFullName').val();
	var customerDetailsCustomerEmail = $('#customerDetailsCustomerEmail').val();
	var customerDetailsCustomerMobile = $('#customerDetailsCustomerMobile').val();
	var customerDetailsCustomerPhone2 = $('#customerDetailsCustomerPhone2').val();
	var customerDetailsCustomerAddress = $('#customerDetailsCustomerAddress').val();
	var customerDetailsCustomerAddress2 = $('#customerDetailsCustomerAddress2').val();
	var customerDetailsCustomerCity = $('#customerDetailsCustomerCity').val();
	var customerDetailsCustomerDistrict = $('#customerDetailsCustomerDistrict option:selected').text();
	var customerDetailsStatus = $('#customerDetailsStatus option:selected').text();

	$.ajax({
		url: 'model/customer/insertCustomer.php',
		method: 'POST',
		data: {
			customerDetailsCustomerFullName: customerDetailsCustomerFullName,
			customerDetailsCustomerEmail: customerDetailsCustomerEmail,
			customerDetailsCustomerMobile: customerDetailsCustomerMobile,
			customerDetailsCustomerPhone2: customerDetailsCustomerPhone2,
			customerDetailsCustomerAddress: customerDetailsCustomerAddress,
			customerDetailsCustomerAddress2: customerDetailsCustomerAddress2,
			customerDetailsCustomerCity: customerDetailsCustomerCity,
			customerDetailsCustomerDistrict: customerDetailsCustomerDistrict,
			customerDetailsStatus: customerDetailsStatus,
		},
		success: function (data) {
			showToastMessage(data, 'customerDetailsMessage');
		},
		complete: function (data) {
			populateLastInsertedID(customerLastInsertedIDFile, 'customerDetailsCustomerID');
			searchTableCreator('customerDetailsTableDiv', customerDetailsSearchTableCreatorFile, 'customerDetailsTable');
			reportsTableCreator('customerReportsTableDiv', customerReportsSearchTableCreatorFile, 'customerReportsTable');
		}
	});
}


// Function to call the insertVendor.php script to insert vendor data to db
function addVendor() {
	var vendorDetailsVendorFullName = $('#vendorDetailsVendorFullName').val();
	var vendorDetailsVendorEmail = $('#vendorDetailsVendorEmail').val();
	var vendorDetailsVendorMobile = $('#vendorDetailsVendorMobile').val();
	var vendorDetailsVendorPhone2 = $('#vendorDetailsVendorPhone2').val();
	var vendorDetailsVendorAddress = $('#vendorDetailsVendorAddress').val();
	var vendorDetailsVendorAddress2 = $('#vendorDetailsVendorAddress2').val();
	var vendorDetailsVendorCity = $('#vendorDetailsVendorCity').val();
	var vendorDetailsVendorDistrict = $('#vendorDetailsVendorDistrict option:selected').text();
	var vendorDetailsStatus = $('#vendorDetailsStatus option:selected').text();

	$.ajax({
		url: 'model/vendor/insertVendor.php',
		method: 'POST',
		data: {
			vendorDetailsVendorFullName: vendorDetailsVendorFullName,
			vendorDetailsVendorEmail: vendorDetailsVendorEmail,
			vendorDetailsVendorMobile: vendorDetailsVendorMobile,
			vendorDetailsVendorPhone2: vendorDetailsVendorPhone2,
			vendorDetailsVendorAddress: vendorDetailsVendorAddress,
			vendorDetailsVendorAddress2: vendorDetailsVendorAddress2,
			vendorDetailsVendorCity: vendorDetailsVendorCity,
			vendorDetailsVendorDistrict: vendorDetailsVendorDistrict,
			vendorDetailsStatus: vendorDetailsStatus,
		},
		success: function (data) {
			showToastMessage(data, 'vendorDetailsMessage');
		},
		complete: function (data) {
			populateLastInsertedID(vendorLastInsertedIDFile, 'vendorDetailsVendorID');
			searchTableCreator('vendorDetailsTableDiv', vendorDetailsSearchTableCreatorFile, 'vendorDetailsTable');
			reportsTableCreator('vendorReportsTableDiv', vendorReportsSearchTableCreatorFile, 'vendorReportsTable');
			$('#purchaseDetailsVendorName').load('model/vendor/getVendorNames.php');
		}
	});
}



var isAddItemSubmitInProgress = false;
var isUpdateItemSubmitInProgress = false;

// Function to call the insertItem.php script to insert item data to db
function addItem() {
	if (isAddItemSubmitInProgress) {
		showToastMessage('Item is already being saved. Please wait.', 'itemDetailsMessage');
		return;
	}

	var itemDetailsItemNumber = $('#itemDetailsItemNumber').val();
	var itemDetailsItemName = $('#itemDetailsItemName').val();
	var itemDetailsUnitAsSold = $('#itemDetailsUnitAsSold').val();
	var itemDetailsDiscount = $('#itemDetailsDiscount').val();
	var itemDetailsQuantity = $('#itemDetailsQuantity').val();
	var itemDetailsUnitPrice = $('#itemDetailsUnitPrice').val();
	var itemDetailsStatus = $('#itemDetailsStatus').val();
	var itemDetailsDescription = $('#itemDetailsDescription').val();
	var $addItemButton = $('#addItem');
	var originalButtonText = $addItemButton.data('original-text') || $addItemButton.text();
	var wasSuccessful = false;
	$addItemButton.data('original-text', originalButtonText);
	isAddItemSubmitInProgress = true;
	$addItemButton.prop('disabled', true).text('Saving...');

	$.ajax({
		url: 'model/item/insertItem.php',
		method: 'POST',
		data: {
			itemDetailsItemNumber: itemDetailsItemNumber,
			itemDetailsItemName: itemDetailsItemName,
			itemDetailsUnitAsSold: itemDetailsUnitAsSold,
			itemDetailsDiscount: itemDetailsDiscount,
			itemDetailsQuantity: itemDetailsQuantity,
			itemDetailsUnitPrice: itemDetailsUnitPrice,
			itemDetailsStatus: itemDetailsStatus,
			itemDetailsDescription: itemDetailsDescription,
		},
		success: function (data) {
			var result = data;
			if (typeof data === 'string') {
				try {
					result = $.parseJSON(data);
				} catch (e) {
					showToastMessage(data, 'itemDetailsMessage');
					return;
				}
			}

			showToastMessage(result, 'itemDetailsMessage');
			if (result && result.success !== undefined) {
				wasSuccessful = !!result.success;
			} else if (typeof data === 'string' && data.toLowerCase().indexOf('success') !== -1) {
				wasSuccessful = true;
			}
		},
		error: function () {
			showToastMessage('Unable to save item right now.', 'itemDetailsMessage');
		},
		complete: function () {
			isAddItemSubmitInProgress = false;
			$addItemButton.prop('disabled', false).text(originalButtonText);

			if (wasSuccessful) {
				var itemForm = $addItemButton.closest('form').get(0);
				if (itemForm) {
					itemForm.reset();
				}
				$('#itemDetailsUnitAsSold').trigger('chosen:updated');
				$('#itemDetailsStatus').trigger('chosen:updated');
				$('#imageContainer').empty();
			}

			populateLastInsertedID(itemLastInsertedIDFile, 'itemDetailsProductID');
			getItemStockToPopulate('itemDetailsItemNumber', getItemStockFile, itemDetailsTotalStock);
			searchTableCreator('itemDetailsTableDiv', itemDetailsSearchTableCreatorFile, 'itemDetailsTable');
			reportsTableCreator('itemReportsTableDiv', itemReportsSearchTableCreatorFile, 'itemReportsTable');
		}
	});
}


// Function to add a new item row in the purchase tab
function addPurchaseItemRow() {
	var rowIndex = $('#purchaseItemsContainer .purchase-item-row').length;
	var rowHtml = '<div class="purchase-item-row border rounded p-3 mb-3 position-relative">' +
		'<button type="button" class="remove-row-icon remove-purchase-item-row" title="Remove item" aria-label="Remove item">&times;</button>' +
		'<div class="form-row">' +
		'<div class="form-group col-md-2">' +
		'<label>Item Number<span class="requiredIcon">*</span></label>' +
		'<input type="text" class="form-control purchase-item-number" name="purchaseItems[' + rowIndex + '][itemNumber]" autocomplete="off">' +
		'<div class="purchase-item-number-suggestions"></div>' +
		'</div>' +
		'<div class="form-group col-md-4">' +
		'<label>Item Name</label>' +
		'<input type="text" class="form-control purchase-item-name" name="purchaseItems[' + rowIndex + '][itemName]" readonly>' +
		'</div>' +
		'<div class="form-group col-md-2">' +
		'<label>Quantity<span class="requiredIcon">*</span></label>' +
		'<input type="number" class="form-control purchase-item-quantity" name="purchaseItems[' + rowIndex + '][quantity]" value="1">' +
		'</div>' +
		'<div class="form-group col-md-2 unit-price-group">' +
		'<label>Unit Price<span class="requiredIcon">*</span></label>' +
		'<input type="hidden" class="purchase-item-unit" name="purchaseItems[' + rowIndex + '][unitAsSold]" value="pcs">' +
		'<div class="input-group unit-price-inline-wrap">' +
		'<input type="text" class="form-control purchase-item-unit-price" name="purchaseItems[' + rowIndex + '][unitPrice]" value="0">' +
		'<div class="input-group-append"><span class="input-group-text unit-badge-inline purchase-item-unit-badge">pcs</span></div>' +
		'</div>' +
		'</div>' +
		'<div class="form-group col-md-2">' +
		'<label>Current Stock</label>' +
		'<input type="text" class="form-control purchase-item-stock" name="purchaseItems[' + rowIndex + '][stock]" readonly>' +
		'</div>' +
		'</div>' +
		'<div class="form-row">' +
		'<div class="form-group col-md-2">' +
		'<label>Line Total</label>' +
		'<input type="text" class="form-control purchase-item-total" name="purchaseItems[' + rowIndex + '][total]" readonly>' +
		'</div>' +
		'</div>' +
		'</div>';
	$('#purchaseItemsContainer').append(rowHtml);
	bindPurchaseItemRowEvents();
}

function bindPurchaseItemRowEvents() {
	$('.purchase-item-row').each(function () {
		var row = $(this);
		row.find('.purchase-item-number').off('keyup').on('keyup', function () {
			showSuggestions($(this).attr('id'), showItemNumberForPurchaseTabFile, $(this).next('.purchase-item-number-suggestions').attr('id'));
		});
		row.find('.purchase-item-number').off('blur').on('blur', function () {
			setTimeout(function () { row.find('.purchase-item-number-suggestions').fadeOut(); }, 200);
		});
		row.find('.purchase-item-number').off('change').on('change', function () {
			populatePurchaseItemRowDetails(row);
		});
		row.find('.purchase-item-number').attr('id', 'purchaseItemNumber_' + $('.purchase-item-row').index(row));
		row.find('.purchase-item-number-suggestions').attr('id', 'purchaseItemNumberSuggestions_' + $('.purchase-item-row').index(row));
		row.find('.purchase-item-quantity, .purchase-item-unit-price').off('input change').on('input change', function () {
			calculatePurchaseItemRowTotal(row);
		});
		row.find('.remove-purchase-item-row').off('click').on('click', function () {
			row.remove();
			calculatePurchaseItemsGrandTotal();
		});
	});
}

$(document).off('click', '.purchase-item-number-suggestions .suggestionsList li').on('click', '.purchase-item-number-suggestions .suggestionsList li', function () {
	var row = $(this).closest('.purchase-item-row');
	row.find('.purchase-item-number').val($(this).text());
	row.find('.purchase-item-number-suggestions').fadeOut();
	populatePurchaseItemRowDetails(row);
});

function populatePurchaseItemRowDetails(row) {
	var itemNumber = row.find('.purchase-item-number').val();
	if (!itemNumber) {
		return;
	}

	// Offline autofill from local catalog (includes zero-stock items)
	if (!navigator.onLine && window.offlineCatalog && typeof window.offlineCatalog.getItem === 'function') {
		if (!window.offlineCatalog.hasCatalog()) {
			showToastMessage(window.offlineCatalog.emptyMessage('items'), 'purchaseDetailsMessage');
			return;
		}
		var localPurchaseItem = window.offlineCatalog.getItem(itemNumber);
		if (!localPurchaseItem) {
			showToastMessage('Item not found in offline catalog: ' + itemNumber, 'purchaseDetailsMessage');
			return;
		}
		row.find('.purchase-item-name').val(localPurchaseItem.itemName || '');
		var purchaseUnitOff = localPurchaseItem.unitAsSold || 'pcs';
		row.find('.purchase-item-unit').val(purchaseUnitOff);
		row.find('.purchase-item-unit-badge').text(purchaseUnitOff);
		row.find('.purchase-item-stock').val(
			window.offlineCatalog.itemAvailable(localPurchaseItem)
		);
		if (localPurchaseItem.unitPrice !== undefined) {
			row.find('.purchase-item-unit-price').val(localPurchaseItem.unitPrice);
		}
		if (window.inventorySync && typeof window.inventorySync.recordKnownStock === 'function') {
			window.inventorySync.recordKnownStock(itemNumber, localPurchaseItem.stock);
		}
		calculatePurchaseItemRowTotal(row);
		return;
	}

	$.ajax({
		url: 'model/item/populateItemDetails.php',
		method: 'POST',
		data: { itemNumber: itemNumber },
		dataType: 'json',
		success: function (data) {
			row.find('.purchase-item-name').val(data.itemName || '');
			var purchaseUnit = data.unitAsSold || 'pcs';
			row.find('.purchase-item-unit').val(purchaseUnit);
			row.find('.purchase-item-unit-badge').text(purchaseUnit);
			row.find('.purchase-item-stock').val(data.stock || '');
			if (data.unitPrice !== undefined) {
				row.find('.purchase-item-unit-price').val(data.unitPrice);
			}
			if (window.inventorySync && typeof window.inventorySync.recordKnownStock === 'function') {
				window.inventorySync.recordKnownStock(itemNumber, data.stock);
			}
			calculatePurchaseItemRowTotal(row);
		}
	});
}

function calculatePurchaseItemRowTotal(row) {
	var quantity = Number(row.find('.purchase-item-quantity').val()) || 0;
	var unitPrice = Number(row.find('.purchase-item-unit-price').val()) || 0;
	row.find('.purchase-item-total').val((unitPrice * quantity).toFixed(2));
	calculatePurchaseItemsGrandTotal();
}

function calculatePurchaseItemsGrandTotal() {
	var grandTotal = 0;
	$('.purchase-item-total').each(function () {
		grandTotal += Number($(this).val()) || 0;
	});
	$('#purchaseItemsGrandTotal').val(grandTotal.toFixed(2));
}

var isPurchaseSubmitInProgress = false;
var isCreditPaymentSubmitInProgress = false;

function addPurchase() {
	if (isPurchaseSubmitInProgress) {
		showToastMessage('Purchase is already being saved. Please wait.', 'purchaseDetailsMessage');
		return;
	}

	var purchaseDetailsPurchaseDate = $('#purchaseDetailsPurchaseDate').val();
	var purchaseDetailsVendorName = $('#purchaseDetailsVendorName').val();
	var purchaseItems = [];
	var $addPurchaseButton = $('#addPurchase');
	var originalButtonText = $addPurchaseButton.data('original-text') || $addPurchaseButton.text();
	var wasSuccessful = false;
	$addPurchaseButton.data('original-text', originalButtonText);

	if (!navigator.onLine && window.inventorySync && typeof window.inventorySync.addToOutbox === 'function') {
		var offlineVendorName = String(purchaseDetailsVendorName || '').trim();
		if (offlineVendorName === '') {
			showToastMessage('Please select a vendor before saving offline.', 'purchaseDetailsMessage');
			return;
		}
		if (window.offlineCatalog) {
			if (!window.offlineCatalog.hasVendors()) {
				showToastMessage(window.offlineCatalog.emptyMessage('vendors'), 'purchaseDetailsMessage');
				return;
			}
			if (!window.offlineCatalog.getVendorByName(offlineVendorName)) {
				showToastMessage('Vendor does not exist in offline catalog. Select a known vendor.', 'purchaseDetailsMessage');
				return;
			}
		}

		$('.purchase-item-row').each(function () {
			var row = $(this);
			purchaseItems.push({
				itemNumber: row.find('.purchase-item-number').val(),
				itemName: row.find('.purchase-item-name').val(),
				quantity: row.find('.purchase-item-quantity').val(),
				unitPrice: row.find('.purchase-item-unit-price').val(),
				stock: row.find('.purchase-item-stock').val(),
				purchaseDate: purchaseDetailsPurchaseDate,
				vendorName: offlineVendorName
			});
		});

		if (purchaseItems.length > 0) {
			var entry = window.inventorySync.addToOutbox('purchase', {
				purchaseDate: purchaseDetailsPurchaseDate,
				vendorName: offlineVendorName,
				items: purchaseItems
			});
			if (entry) {
				window.inventorySync.setSyncStatusUI();
				showToastMessage('Offline mode: purchase queued and will sync automatically when the connection returns.', 'purchaseDetailsMessage');
				var purchaseForm = $addPurchaseButton.closest('form').get(0);
				if (purchaseForm) {
					purchaseForm.reset();
				}
				isPurchaseSubmitInProgress = false;
				$addPurchaseButton.prop('disabled', false).text(originalButtonText);
				return;
			}
		}
	}

	$('.purchase-item-row').each(function () {
		var row = $(this);
		purchaseItems.push({
			itemNumber: row.find('.purchase-item-number').val(),
			itemName: row.find('.purchase-item-name').val(),
			quantity: row.find('.purchase-item-quantity').val(),
			unitPrice: row.find('.purchase-item-unit-price').val(),
			stock: row.find('.purchase-item-stock').val()
		});
	});

	if (purchaseItems.length === 0) {
		showToastMessage('Please add at least one item before saving the purchase.', 'purchaseDetailsMessage');
		return;
	}

	isPurchaseSubmitInProgress = true;
	$addPurchaseButton.prop('disabled', true).text('Saving...');

	$.ajax({
		url: 'model/purchase/insertPurchase.php',
		method: 'POST',
		data: {
			purchaseDetailsPurchaseDate: purchaseDetailsPurchaseDate,
			purchaseDetailsVendorName: purchaseDetailsVendorName,
			purchaseItems: JSON.stringify(purchaseItems)
		},
		success: function (data) {
			try {
				var result = typeof data === 'string' ? $.parseJSON(data) : data;
				if (result && result.success !== undefined) {
					showToastMessage(result.message || 'Purchase saved successfully.', 'purchaseDetailsMessage');
					if (result.success) {
						wasSuccessful = true;
					}
				} else {
					showToastMessage(data, 'purchaseDetailsMessage');
				}
			} catch (e) {
				showToastMessage(data, 'purchaseDetailsMessage');
			}
		},
		error: function () {
			showToastMessage('Unable to save purchase right now.', 'purchaseDetailsMessage');
		},
		complete: function () {
			isPurchaseSubmitInProgress = false;
			$addPurchaseButton.prop('disabled', false).text(originalButtonText);

			if (wasSuccessful) {
				var purchaseForm = $addPurchaseButton.closest('form').get(0);
				if (purchaseForm) {
					purchaseForm.reset();
				}
			}

			populateLastInsertedID(purchaseLastInsertedIDFile, 'purchaseDetailsPurchaseID');
			searchTableCreator('purchaseDetailsTableDiv', purchaseDetailsSearchTableCreatorFile, 'purchaseDetailsTable');
			reportsPurchaseTableCreator('purchaseReportsTableDiv', purchaseReportsSearchTableCreatorFile, 'purchaseReportsTable');
			searchTableCreator('itemDetailsTableDiv', itemDetailsSearchTableCreatorFile, 'itemDetailsTable');
			reportsTableCreator('itemReportsTableDiv', itemReportsSearchTableCreatorFile, 'itemReportsTable');
		}
	});
}


// Function to add a new item row in the stock out tab
function addSaleItemRow() {
	var rowIndex = $('#saleItemsContainer .sale-item-row').length;
	var rowHtml = '<div class="sale-item-row border rounded p-3 mb-3 position-relative">' +
		'<button type="button" class="remove-row-icon remove-sale-item-row" title="Remove item" aria-label="Remove item">&times;</button>' +
		'<div class="form-row">' +
		'<div class="form-group col-md-3">' +
		'<label>Item Number<span class="requiredIcon">*</span></label>' +
		'<input type="text" class="form-control sale-item-number" name="saleItems[' + rowIndex + '][itemNumber]" autocomplete="off">' +
		'<div class="sale-item-number-suggestions"></div>' +
		'<input type="hidden" class="sale-item-name" name="saleItems[' + rowIndex + '][itemName]">' +
		'</div>' +
		'<div class="form-group col-md-2">' +
		'<label>Quantity<span class="requiredIcon">*</span></label>' +
		'<input type="number" class="form-control sale-item-quantity" name="saleItems[' + rowIndex + '][quantity]" value="1">' +
		'</div>' +
		'<div class="form-group col-md-2 unit-price-group">' +
		'<label>Unit Price<span class="requiredIcon">*</span></label>' +
		'<input type="hidden" class="sale-item-unit" name="saleItems[' + rowIndex + '][unitAsSold]" value="pcs">' +
		'<input type="hidden" class="sale-item-discount" name="saleItems[' + rowIndex + '][discount]" value="0">' +
		'<div class="input-group unit-price-inline-wrap">' +
		'<input type="text" class="form-control sale-item-unit-price" name="saleItems[' + rowIndex + '][unitPrice]" value="0">' +
		'<div class="input-group-append"><span class="input-group-text unit-badge-inline sale-item-unit-badge"></span></div>' +
		'</div>' +
		'</div>' +
		'<div class="form-group col-md-1">' +
		'<label>Reason</label>' +
		'<select class="form-control sale-item-reason" name="saleItems[' + rowIndex + '][reason]">' +
		'<option value="Sales">Sales</option><option value="Damaged">Damaged</option><option value="Gifted">Gifted</option><option value="Expired">Expired</option><option value="Returned">Returned</option><option value="Other">Other</option>' +
		'</select>' +
		'</div>' +
		'<div class="form-group col-md-2">' +
		'<label>Total Stock</label>' +
		'<input type="text" class="form-control sale-item-stock" name="saleItems[' + rowIndex + '][stock]" readonly>' +
		'</div>' +
		'<div class="form-group col-md-2">' +
		'<label>Line Total</label>' +
		'<input type="text" class="form-control sale-item-total" name="saleItems[' + rowIndex + '][total]" readonly>' +
		'</div>' +
		'</div>' +
		'</div>';
	$('#saleItemsContainer').append(rowHtml);
	bindSaleItemRowEvents();
}

function bindSaleItemRowEvents() {
	$('.sale-item-row').each(function () {
		var row = $(this);
		row.find('.sale-item-number').off('keyup').on('keyup', function () {
			showSuggestions($(this).attr('id'), showItemNumberForSaleTabFile, $(this).next('.sale-item-number-suggestions').attr('id'));
		});
		row.find('.sale-item-number').off('blur').on('blur', function () {
			setTimeout(function () { row.find('.sale-item-number-suggestions').fadeOut(); }, 200);
		});
		row.find('.sale-item-number').off('change').on('change', function () {
			populateSaleItemRowDetails(row);
		});
		row.find('.sale-item-number').attr('id', 'saleItemNumber_' + $('.sale-item-row').index(row));
		row.find('.sale-item-number-suggestions').attr('id', 'saleItemNumberSuggestions_' + $('.sale-item-row').index(row));
		row.find('.sale-item-quantity, .sale-item-unit-price, .sale-item-discount').off('input change').on('input change', function () {
			calculateSaleItemRowTotal(row);
		});
		row.find('.remove-sale-item-row').off('click').on('click', function () {
			row.remove();
			calculateSaleItemsGrandTotal();
		});
	});
}

$(document).off('click', '.sale-item-number-suggestions .suggestionsList li').on('click', '.sale-item-number-suggestions .suggestionsList li', function () {
	var row = $(this).closest('.sale-item-row');
	row.find('.sale-item-number').val($(this).text());
	row.find('.sale-item-number-suggestions').fadeOut();
	populateSaleItemRowDetails(row);
});

function populateSaleItemRowDetails(row) {
	var itemNumber = row.find('.sale-item-number').val();
	if (!itemNumber) {
		return;
	}

	// Offline autofill from local catalog
	if (!navigator.onLine && window.offlineCatalog && typeof window.offlineCatalog.getItem === 'function') {
		if (!window.offlineCatalog.hasCatalog()) {
			showToastMessage(window.offlineCatalog.emptyMessage('items'), 'saleDetailsMessage');
			return;
		}
		var localItem = window.offlineCatalog.getItem(itemNumber);
		if (!localItem) {
			showToastMessage('Item not found in offline catalog: ' + itemNumber, 'saleDetailsMessage');
			return;
		}
		var avail = window.offlineCatalog.itemAvailable(localItem);
		row.find('.sale-item-name').val(localItem.itemName || '');
		var saleUnitOff = localItem.unitAsSold || 'pcs';
		row.find('.sale-item-unit').val(saleUnitOff);
		row.find('.sale-item-unit-badge').text(saleUnitOff);
		row.find('.sale-item-stock').val(avail);
		if (localItem.unitPrice !== undefined) {
			row.find('.sale-item-unit-price').val(localItem.unitPrice);
		}
		if (localItem.discount !== undefined) {
			row.find('.sale-item-discount').val(localItem.discount);
		}
		if (window.inventorySync && typeof window.inventorySync.recordKnownStock === 'function') {
			window.inventorySync.recordKnownStock(itemNumber, localItem.stock);
		}
		calculateSaleItemRowTotal(row);
		return;
	}

	$.ajax({
		url: 'model/item/populateItemDetails.php',
		method: 'POST',
		data: { itemNumber: itemNumber },
		dataType: 'json',
		success: function (data) {
			row.find('.sale-item-name').val(data.itemName || '');
			var saleUnit = data.unitAsSold || 'pcs';
			row.find('.sale-item-unit').val(saleUnit);
			row.find('.sale-item-unit-badge').text(saleUnit);
			row.find('.sale-item-stock').val(data.stock || '');
			if (data.unitPrice !== undefined) {
				row.find('.sale-item-unit-price').val(data.unitPrice);
			}
			if (data.discount !== undefined) {
				row.find('.sale-item-discount').val(data.discount);
			}
			if (window.inventorySync && typeof window.inventorySync.recordKnownStock === 'function') {
				window.inventorySync.recordKnownStock(itemNumber, data.stock);
			}
			calculateSaleItemRowTotal(row);
		}
	});
}

function calculateSaleItemRowTotal(row) {
	var quantity = Number(row.find('.sale-item-quantity').val()) || 0;
	var unitPrice = Number(row.find('.sale-item-unit-price').val()) || 0;
	var discount = Number(row.find('.sale-item-discount').val()) || 0;
	row.find('.sale-item-total').val((unitPrice * ((100 - discount) / 100) * quantity).toFixed(2));
	calculateSaleItemsGrandTotal();
}

function calculateSaleItemsGrandTotal() {
	var grandTotal = 0;
	$('.sale-item-total').each(function () {
		grandTotal += Number($(this).val()) || 0;
	});
	$('#saleItemsGrandTotal').val(grandTotal.toFixed(2));
}

var isSaleSubmitInProgress = false;

function addSale() {
	if (isSaleSubmitInProgress) {
		showToastMessage('Stock out is already being saved. Please wait.', 'saleDetailsMessage');
		return;
	}

	var saleDetailsCustomerID = $('#saleDetailsCustomerID').val();
	var saleDetailsCustomerName = $('#saleDetailsCustomerName').val();
	var saleDetailsSaleDate = $('#saleDetailsSaleDate').val();
	var saleDetailsAmountPaid = $('#saleDetailsAmountPaid').val();
	var saleItems = [];
	var $addSaleButton = $('#addSaleButton');
	var originalButtonText = $addSaleButton.data('original-text') || $addSaleButton.text();
	var wasSuccessful = false;
	$addSaleButton.data('original-text', originalButtonText);

	if (!navigator.onLine && window.inventorySync && typeof window.inventorySync.addToOutbox === 'function') {
		// Prevent offline sale if customer is missing or invalid (mirrors server-side check)
		var offlineCustomerId = String(saleDetailsCustomerID || '').trim();
		var offlineCustomerName = String(saleDetailsCustomerName || '').trim();
		if (offlineCustomerId === '' || !/^\d+$/.test(offlineCustomerId) || parseInt(offlineCustomerId, 10) <= 0) {
			showToastMessage('Customer does not exist. Enter a valid Customer ID before saving offline.', 'saleDetailsMessage');
			return;
		}
		if (offlineCustomerName === '') {
			showToastMessage('Customer does not exist. Select a known customer (name must be loaded) before saving offline.', 'saleDetailsMessage');
			return;
		}

		$('.sale-item-row').each(function () {
			var row = $(this);
			saleItems.push({
				itemNumber: row.find('.sale-item-number').val(),
				itemName: row.find('.sale-item-name').val(),
				quantity: row.find('.sale-item-quantity').val(),
				unitPrice: row.find('.sale-item-unit-price').val(),
				discount: row.find('.sale-item-discount').val(),
				reason: row.find('.sale-item-reason').val(),
				stock: row.find('.sale-item-stock').val()
			});
		});

		if (saleItems.length === 0) {
			showToastMessage('Please add at least one item before saving the stock out.', 'saleDetailsMessage');
			return;
		}

		// Offline insufficient-stock check using last known stock (form/cache) + pending offline purchases/sales
		var offlineStockNeeded = {};
		for (var si = 0; si < saleItems.length; si++) {
			var sItem = saleItems[si];
			var sNum = String(sItem.itemNumber || '').trim();
			var sQty = parseInt(sItem.quantity, 10);
			if (sNum === '') {
				showToastMessage('Please enter an item number for each row.', 'saleDetailsMessage');
				return;
			}
			if (isNaN(sQty) || sQty <= 0) {
				showToastMessage('Please enter a valid quantity for each row.', 'saleDetailsMessage');
				return;
			}
			offlineStockNeeded[sNum] = (offlineStockNeeded[sNum] || 0) + sQty;
		}
		for (var itemKey in offlineStockNeeded) {
			if (!Object.prototype.hasOwnProperty.call(offlineStockNeeded, itemKey)) {
				continue;
			}
			var neededQty = offlineStockNeeded[itemKey];
			var baselineStock = '';
			$('.sale-item-row').each(function () {
				var row = $(this);
				if (String(row.find('.sale-item-number').val() || '').trim() === itemKey) {
					baselineStock = row.find('.sale-item-stock').val();
					return false;
				}
			});
			var available = null;
			if (window.inventorySync.getOfflineAvailableStock) {
				available = window.inventorySync.getOfflineAvailableStock(itemKey, baselineStock);
			} else {
				var parsedBase = parseInt(baselineStock, 10);
				available = isNaN(parsedBase) ? null : parsedBase;
			}
			if (available === null) {
				showToastMessage(
					'Cannot verify stock for item ' + itemKey + ' offline (last DB stock unknown). Load the item while online, or make an offline purchase for this product first.',
					'saleDetailsMessage'
				);
				return;
			}
			if (neededQty > available) {
				showToastMessage(
					'Insufficient stock for item ' + itemKey + ' (available offline: ' + available + ', requested: ' + neededQty + '). Make an offline purchase first, then try stock out again.',
					'saleDetailsMessage'
				);
				return;
			}
		}

		var entry = window.inventorySync.addToOutbox('sale', {
			customerID: offlineCustomerId,
			customerName: offlineCustomerName,
			saleDate: saleDetailsSaleDate,
			amountPaid: saleDetailsAmountPaid,
			items: saleItems
		});
		if (entry) {
			window.inventorySync.setSyncStatusUI();
			showToastMessage('Offline mode: stock out queued and will sync automatically when the connection returns.', 'saleDetailsMessage');
			var saleForm = $addSaleButton.closest('form').get(0);
			if (saleForm) {
				saleForm.reset();
			}
			$('#saleDetailsImageContainer').empty();
			isSaleSubmitInProgress = false;
			$addSaleButton.prop('disabled', false).text(originalButtonText);
			return;
		}
		// If addToOutbox returned null (e.g. free plan upgrade modal), stop here
		return;
	}

	$('.sale-item-row').each(function () {
		var row = $(this);
		saleItems.push({
			itemNumber: row.find('.sale-item-number').val(),
			itemName: row.find('.sale-item-name').val(),
			quantity: row.find('.sale-item-quantity').val(),
			unitPrice: row.find('.sale-item-unit-price').val(),
			discount: row.find('.sale-item-discount').val(),
			reason: row.find('.sale-item-reason').val(),
			stock: row.find('.sale-item-stock').val()
		});
	});

	if (saleItems.length === 0) {
		showToastMessage('Please add at least one item before saving the stock out.', 'saleDetailsMessage');
		return;
	}

	isSaleSubmitInProgress = true;
	$addSaleButton.prop('disabled', true).text('Saving...');

	$.ajax({
		url: 'model/sale/insertSale.php',
		method: 'POST',
		data: {
			saleDetailsCustomerID: saleDetailsCustomerID,
			saleDetailsCustomerName: saleDetailsCustomerName,
			saleDetailsSaleDate: saleDetailsSaleDate,
			saleDetailsAmountPaid: saleDetailsAmountPaid,
			saleItems: JSON.stringify(saleItems)
		},
		success: function (data) {
			var result = data;
			if (typeof data === 'string') {
				try {
					result = $.parseJSON(data);
				} catch (e) {
					showToastMessage('Unable to save stock out right now.', 'saleDetailsMessage');
					return;
				}
			}

			if (result && result.success !== undefined) {
				showToastMessage(result.message || 'Stock out saved successfully.', 'saleDetailsMessage');
				if (result.saleReference) {
					$('#saleDetailsSaleID').val(result.saleReference);
				}
				if (result.success) {
					wasSuccessful = true;
				}
			} else {
				showToastMessage(data, 'saleDetailsMessage');
			}
		},
		error: function () {
			showToastMessage('Unable to save stock out right now.', 'saleDetailsMessage');
		},
		complete: function () {
			isSaleSubmitInProgress = false;
			$addSaleButton.prop('disabled', false).text(originalButtonText);

			if (wasSuccessful) {
				var saleForm = $addSaleButton.closest('form').get(0);
				if (saleForm) {
					saleForm.reset();
				}
				$('#saleDetailsImageContainer').empty();
			}

			populateLastInsertedID(saleLastInsertedIDFile, 'saleDetailsSaleID');
			searchTableCreator('saleDetailsTableDiv', saleDetailsSearchTableCreatorFile, 'saleDetailsTable');
			reportsSaleTableCreator('saleReportsTableDiv', saleReportsSearchTableCreatorFile, 'saleReportsTable');
			searchTableCreator('itemDetailsTableDiv', itemDetailsSearchTableCreatorFile, 'itemDetailsTable');
			reportsTableCreator('itemReportsTableDiv', itemReportsSearchTableCreatorFile, 'itemReportsTable');
		}
	});
}


// Function to send itemNumber so that item details can be pulled from db
// to be displayed on item details tab
function getItemDetailsToPopulate() {
	// Get the itemNumber entered in the text box
	var itemNumber = $('#itemDetailsItemNumber').val();
	var defaultImgUrl = 'data/item_images/imageNotAvailable.jpg';
	var defaultImageData = '<img class="img-fluid" src="data/item_images/imageNotAvailable.jpg">';

	// Call the populateItemDetails.php script to get item details
	// relevant to the itemNumber which the user entered
	$.ajax({
		url: 'model/item/populateItemDetails.php',
		method: 'POST',
		data: { itemNumber: itemNumber },
		dataType: 'json',
		success: function (data) {
			//$('#itemDetailsItemNumber').val(data.itemNumber);
			$('#itemDetailsProductID').val(data.productID);
			$('#itemDetailsItemName').val(data.itemName);
			$('#itemDetailsUnitAsSold').val(data.unitAsSold || 'pcs');
			$('#itemDetailsUnitAsSold').trigger('chosen:updated');
			$('#itemDetailsDiscount').val(data.discount);
			$('#itemDetailsTotalStock').val(data.stock);
			$('#itemDetailsUnitPrice').val(data.unitPrice);
			$('#itemDetailsDescription').val(data.description);
			$('#itemDetailsStatus').val(data.status).trigger("chosen:updated");

			newImgUrl = 'data/item_images/' + data.itemNumber + '/' + data.imageURL;

			// Set the item image
			if (data.imageURL == 'imageNotAvailable.jpg' || data.imageURL == '') {
				$('#imageContainer').html(defaultImageData);
			} else {
				$('#imageContainer').html('<img class="img-fluid" src="' + newImgUrl + '">');
			}
		}
	});
}


// Function to send itemNumber so that item details can be pulled from db
// to be displayed on sale details tab
function getItemDetailsToPopulateForSaleTab() {
	// Get the itemNumber entered in the text box
	var itemNumber = $('#saleDetailsItemNumber').val();
	var defaultImgUrl = 'data/item_images/imageNotAvailable.jpg';
	var defaultImageData = '<img class="img-fluid" src="data/item_images/imageNotAvailable.jpg">';

	// Call the populateItemDetails.php script to get item details
	// relevant to the itemNumber which the user entered
	$.ajax({
		url: 'model/item/populateItemDetails.php',
		method: 'POST',
		data: { itemNumber: itemNumber },
		dataType: 'json',
		success: function (data) {
			//$('#saleDetailsItemNumber').val(data.itemNumber);
			$('#saleDetailsItemName').val(data.itemName);
			$('#saleDetailsDiscount').val(data.discount);
			$('#saleDetailsTotalStock').val(data.stock);
			$('#saleDetailsUnitPrice').val(data.unitPrice);
			if (window.inventorySync && typeof window.inventorySync.recordKnownStock === 'function') {
				window.inventorySync.recordKnownStock(itemNumber, data.stock);
			}

			newImgUrl = 'data/item_images/' + data.itemNumber + '/' + data.imageURL;

			// Set the item image
			if (data.imageURL == 'imageNotAvailable.jpg' || data.imageURL == '') {
				$('#saleDetailsImageContainer').html(defaultImageData);
			} else {
				$('#saleDetailsImageContainer').html('<img class="img-fluid" src="' + newImgUrl + '">');
			}
		},
		complete: function () {
			//$('#saleDetailsDiscount, #saleDetailsQuantity, #saleDetailsUnitPrice').trigger('change');
			calculateTotalInSaleTab();
		}
	});
}


// Function to send itemNumber so that item name can be pulled from db
function getItemName(itemNumberTextBoxID, scriptPath, itemNameTextbox) {
	// Get the itemNumber entered in the text box
	var itemNumber = $('#' + itemNumberTextBoxID).val();

	// Call the script to get item details
	$.ajax({
		url: scriptPath,
		method: 'POST',
		data: { itemNumber: itemNumber },
		dataType: 'json',
		success: function (data) {
			$('#' + itemNameTextbox).val(data.itemName);
		},
		error: function (xhr, ajaxOptions, thrownError) {
		}
	});
}


// Function to send itemNumber so that item stock can be pulled from db
function getItemStockToPopulate(itemNumberTextbox, scriptPath, stockTextbox) {
	// Get the itemNumber entered in the text box
	var itemNumber = $('#' + itemNumberTextbox).val();

	// Call the script to get stock details
	$.ajax({
		url: scriptPath,
		method: 'POST',
		data: { itemNumber: itemNumber },
		dataType: 'json',
		success: function (data) {
			$('#' + stockTextbox).val(data.stock);
			if (window.inventorySync && typeof window.inventorySync.recordKnownStock === 'function') {
				window.inventorySync.recordKnownStock(itemNumber, data.stock);
			}
		},
		error: function (xhr, ajaxOptions, thrownError) {
			//alert(xhr.status);
			//alert(thrownError);
			//console.warn(xhr.responseText)
		}
	});
}


// Function to populate last inserted ID
function populateLastInsertedID(scriptPath, textBoxID) {
	$.ajax({
		url: scriptPath,
		method: 'POST',
		success: function (data) {
			var value = '';
			if (typeof data === 'string') {
				value = $.trim(data);
			} else if (data !== null && data !== undefined) {
				value = data;
			}
			$('#' + textBoxID).val(value);
		}
	});
}


// Function to show suggestions
function showSuggestions(textBoxID, scriptPath, suggestionsDivID, extraData) {
	// Get the value entered by the user
	var textBoxValue = $('#' + textBoxID).val();

	if (textBoxValue == '') {
		$('#' + suggestionsDivID).fadeOut().empty();
		return;
	}

	// Offline: local catalog (items / customers) — same suggestion UI
	if (!navigator.onLine && window.offlineCatalog && typeof window.offlineCatalog.applyOfflineSuggestions === 'function') {
		var handled = window.offlineCatalog.applyOfflineSuggestions(textBoxID, scriptPath, suggestionsDivID);
		if (handled) {
			return;
		}
	}

	var requestData = { textBoxValue: textBoxValue };
	if (extraData && typeof extraData === 'object') {
		requestData = $.extend({}, requestData, extraData);
	}
	$.ajax({
		url: scriptPath,
		method: 'POST',
		data: requestData,
		success: function (data) {
			$('#' + suggestionsDivID).fadeIn();
			$('#' + suggestionsDivID).html(data);
		}
	});
}


// Function to delte item from db
function deleteItem() {
	// Get the item number entered by the user
	var itemDetailsItemNumber = $('#itemDetailsItemNumber').val();

	// Call the deleteItem.php script only if there is a value in the
	// item number textbox
	if (itemDetailsItemNumber != '') {
		$.ajax({
			url: 'model/item/deleteItem.php',
			method: 'POST',
			data: { itemDetailsItemNumber: itemDetailsItemNumber },
			success: function (data) {
				showToastMessage(data, 'itemDetailsMessage');
			},
			complete: function () {
				searchTableCreator('itemDetailsTableDiv', itemDetailsSearchTableCreatorFile, 'itemDetailsTable');
				reportsTableCreator('itemReportsTableDiv', itemReportsSearchTableCreatorFile, 'itemReportsTable');
			}
		});
	}
}


// Function to delete item from db
function deleteCustomer() {
	// Get the customerID entered by the user
	var customerDetailsCustomerID = $('#customerDetailsCustomerID').val();

	// Call the deleteCustomer.php script only if there is a value in the
	// item number textbox
	if (customerDetailsCustomerID != '') {
		$.ajax({
			url: 'model/customer/deleteCustomer.php',
			method: 'POST',
			data: { customerDetailsCustomerID: customerDetailsCustomerID },
			success: function (data) {
				showToastMessage(data, 'customerDetailsMessage');
			},
			complete: function () {
				searchTableCreator('customerDetailsTableDiv', customerDetailsSearchTableCreatorFile, 'customerDetailsTable');
				reportsTableCreator('customerReportsTableDiv', customerReportsSearchTableCreatorFile, 'customerReportsTable');
			}
		});
	}
}


// Function to delete vendor from db
function deleteVendor() {
	// Get the vendorID entered by the user
	var vendorDetailsVendorID = $('#vendorDetailsVendorID').val();

	// Call the deleteVendor.php script only if there is a value in the
	// vendor ID textbox
	if (vendorDetailsVendorID != '') {
		$.ajax({
			url: 'model/vendor/deleteVendor.php',
			method: 'POST',
			data: { vendorDetailsVendorID: vendorDetailsVendorID },
			success: function (data) {
				showToastMessage(data, 'vendorDetailsMessage');
			},
			complete: function () {
				searchTableCreator('vendorDetailsTableDiv', vendorDetailsSearchTableCreatorFile, 'vendorDetailsTable');
				reportsTableCreator('vendorReportsTableDiv', vendorReportsSearchTableCreatorFile, 'vendorReportsTable');
			}
		});
	}
}


// Function to send customerID so that customer details can be pulled from db
// to be displayed on customer details tab
function getCustomerDetailsToPopulate() {
	// Get the customerID entered in the text box
	var customerDetailsCustomerID = $('#customerDetailsCustomerID').val();

	// Call the populateItemDetails.php script to get item details
	// relevant to the itemNumber which the user entered
	$.ajax({
		url: 'model/customer/populateCustomerDetails.php',
		method: 'POST',
		data: { customerID: customerDetailsCustomerID },
		dataType: 'json',
		success: function (data) {
			//$('#customerDetailsCustomerID').val(data.customerID);
			$('#customerDetailsCustomerFullName').val(data.fullName);
			$('#customerDetailsCustomerMobile').val(data.mobile);
			$('#customerDetailsCustomerPhone2').val(data.phone2);
			$('#customerDetailsCustomerEmail').val(data.email);
			$('#customerDetailsCustomerAddress').val(data.address);
			$('#customerDetailsCustomerAddress2').val(data.address2);
			$('#customerDetailsCustomerCity').val(data.city);
			$('#customerDetailsCustomerDistrict').val(data.district).trigger("chosen:updated");
			$('#customerDetailsStatus').val(data.status).trigger("chosen:updated");
		}
	});
}


// Function to send customerID so that customer details can be pulled from db
// to be displayed on sale details tab
function getCustomerDetailsToPopulateSaleTab() {
	// Get the customerID entered in the text box
	var customerDetailsCustomerID = $('#saleDetailsCustomerID').val();

	// Offline: fill name from local customer catalog
	if (!navigator.onLine && window.offlineCatalog && typeof window.offlineCatalog.getCustomer === 'function') {
		if (!window.offlineCatalog.hasCustomers()) {
			showToastMessage(window.offlineCatalog.emptyMessage('customers'), 'saleDetailsMessage');
			return;
		}
		var localCust = window.offlineCatalog.getCustomer(customerDetailsCustomerID);
		if (!localCust) {
			showToastMessage('Customer does not exist in offline catalog.', 'saleDetailsMessage');
			$('#saleDetailsCustomerName').val('');
			return;
		}
		$('#saleDetailsCustomerName').val(localCust.fullName || '');
		return;
	}

	// Call the populateCustomerDetails.php script to get customer details
	// relevant to the customerID which the user entered
	$.ajax({
		url: 'model/customer/populateCustomerDetails.php',
		method: 'POST',
		data: { customerID: customerDetailsCustomerID },
		dataType: 'json',
		success: function (data) {
			//$('#saleDetailsCustomerID').val(data.customerID);
			$('#saleDetailsCustomerName').val(data.fullName);
		}
	});
}

// Function to send customerID so that customer details can be pulled from db
// to be displayed on the credit book tab
function getCustomerDetailsToPopulateCreditTab() {
	var customerDetailsCustomerID = $('#creditCustomerID').val();

	$.ajax({
		url: 'model/customer/populateCustomerDetails.php',
		method: 'POST',
		data: { customerID: customerDetailsCustomerID },
		dataType: 'json',
		success: function (data) {
			$('#creditCustomerName').val(data.fullName);
		}
	});
}


// Function to send vendorID so that vendor details can be pulled from db
// to be displayed on vendor details tab
function getVendorDetailsToPopulate() {
	// Get the vendorID entered in the text box
	var vendorDetailsVendorID = $('#vendorDetailsVendorID').val();

	// Call the populateVendorDetails.php script to get vendor details
	// relevant to the vendorID which the user entered
	$.ajax({
		url: 'model/vendor/populateVendorDetails.php',
		method: 'POST',
		data: { vendorDetailsVendorID: vendorDetailsVendorID },
		dataType: 'json',
		success: function (data) {
			//$('#vendorDetailsVendorID').val(data.vendorID);
			$('#vendorDetailsVendorFullName').val(data.fullName);
			$('#vendorDetailsVendorMobile').val(data.mobile);
			$('#vendorDetailsVendorPhone2').val(data.phone2);
			$('#vendorDetailsVendorEmail').val(data.email);
			$('#vendorDetailsVendorAddress').val(data.address);
			$('#vendorDetailsVendorAddress2').val(data.address2);
			$('#vendorDetailsVendorCity').val(data.city);
			$('#vendorDetailsVendorDistrict').val(data.district).trigger("chosen:updated");
			$('#vendorDetailsStatus').val(data.status).trigger("chosen:updated");
		}
	});
}


// Function to send purchaseID so that purchase details can be pulled from db
// to be displayed on purchase details tab
function getPurchaseDetailsToPopulate() {
	// Get the purchaseID entered in the text box
	var purchaseDetailsPurchaseID = $('#purchaseDetailsPurchaseID').val();

	// Call the populatePurchaseDetails.php script to get item details
	// relevant to the itemNumber which the user entered
	$.ajax({
		url: 'model/purchase/populatePurchaseDetails.php',
		method: 'POST',
		data: { purchaseDetailsPurchaseID: purchaseDetailsPurchaseID },
		dataType: 'json',
		success: function (data) {
			if (!data || !data.success) {
				showToastMessage((data && data.message) || 'Transaction was not found.', 'purchaseDetailsMessage');
				return;
			}
			$('#purchaseDetailsPurchaseDate').val(data.purchaseDate);
			$('#purchaseDetailsVendorName').val(data.vendorName).trigger("chosen:updated");
			$('#purchaseItemsContainer').empty();
			$.each(data.items, function (_, item) {
				addPurchaseItemRow();
				var row = $('#purchaseItemsContainer .purchase-item-row').last();
				row.find('.purchase-item-number').val(item.itemNumber);
				row.find('.purchase-item-name').val(item.itemName);
				row.find('.purchase-item-quantity').val(item.quantity);
				row.find('.purchase-item-unit-price').val(item.unitPrice);
				calculatePurchaseItemRowTotal(row);
			});
			showToastMessage('Transaction loaded. Update it when you are finished editing.', 'purchaseDetailsMessage');
		},
		complete: function () {
			calculateTotalInPurchaseTab();
			getItemStockToPopulate('purchaseDetailsItemNumber', getItemStockFile, 'purchaseDetailsCurrentStock');
		}
	});
}


// Function to send saleID so that sale details can be pulled from db
// to be displayed on sale details tab
function getSaleDetailsToPopulate() {
	// Get the saleID entered in the text box
	var saleDetailsSaleID = $('#saleDetailsSaleID').val();

	// Call the populateSaleDetails.php script to get item details
	// relevant to the itemNumber which the user entered
	$.ajax({
		url: 'model/sale/populateSaleDetails.php',
		method: 'POST',
		data: { saleDetailsSaleID: saleDetailsSaleID },
		dataType: 'json',
		success: function (data) {
			if (!data || !data.success) {
				showToastMessage((data && data.message) || 'Transaction was not found.', 'saleDetailsMessage');
				return;
			}
			$('#saleDetailsItemNumber').val(data.itemNumber);
			$('#saleDetailsCustomerID').val(data.customerID);
			$('#saleDetailsCustomerName').val(data.customerName);
			$('#saleDetailsSaleDate').val(data.saleDate);
			$('#saleDetailsAmountPaid').val(data.amountPaid);
			$('#saleItemsContainer').empty();
			$.each(data.items, function (_, item) {
				addSaleItemRow();
				var row = $('#saleItemsContainer .sale-item-row').last();
				row.find('.sale-item-number').val(item.itemNumber);
				row.find('.sale-item-name').val(item.itemName);
				row.find('.sale-item-quantity').val(item.quantity);
				row.find('.sale-item-unit-price').val(item.unitPrice);
				row.find('.sale-item-discount').val(item.discount);
				row.find('.sale-item-reason').val(item.reason || 'Sales');
				calculateSaleItemRowTotal(row);
			});
			showToastMessage('Transaction loaded. Update it when you are finished editing.', 'saleDetailsMessage');
		},
		complete: function () {
			calculateTotalInSaleTab();
			getItemStockToPopulate('saleDetailsItemNumber', getItemStockFile, 'saleDetailsTotalStock');
		}
	});
}


// Function to call the upateItemDetails.php script to UPDATE item data in db
function updateItem() {
	if (isUpdateItemSubmitInProgress) {
		showToastMessage('Item update is already in progress. Please wait.', 'itemDetailsMessage');
		return;
	}

	var itemDetailsItemNumber = $('#itemDetailsItemNumber').val();
	var itemDetailsItemName = $('#itemDetailsItemName').val();
	var itemDetailsUnitAsSold = $('#itemDetailsUnitAsSold').val();
	var itemDetailsDiscount = $('#itemDetailsDiscount').val();
	var itemDetailsQuantity = $('#itemDetailsQuantity').val();
	var itemDetailsUnitPrice = $('#itemDetailsUnitPrice').val();
	var itemDetailsStatus = $('#itemDetailsStatus').val();
	var itemDetailsDescription = $('#itemDetailsDescription').val();
	var $updateItemButton = $('#updateItemDetailsButton');
	var originalButtonText = $updateItemButton.data('original-text') || $updateItemButton.text();
	var wasSuccessful = false;
	$updateItemButton.data('original-text', originalButtonText);
	isUpdateItemSubmitInProgress = true;
	$updateItemButton.prop('disabled', true).text('Saving...');

	$.ajax({
		url: 'model/item/updateItemDetails.php',
		method: 'POST',
		data: {
			itemNumber: itemDetailsItemNumber,
			itemDetailsItemName: itemDetailsItemName,
			itemDetailsUnitAsSold: itemDetailsUnitAsSold,
			itemDetailsDiscount: itemDetailsDiscount,
			itemDetailsQuantity: itemDetailsQuantity,
			itemDetailsUnitPrice: itemDetailsUnitPrice,
			itemDetailsStatus: itemDetailsStatus,
			itemDetailsDescription: itemDetailsDescription,
		},
		success: function (data) {
			var result = data;
			if (typeof data === 'string') {
				try {
					result = $.parseJSON(data);
				} catch (e) {
					showToastMessage('Unable to update item right now.', 'itemDetailsMessage');
					return;
				}
			}

			showToastMessage(result.alertMessage || result.message || result, 'itemDetailsMessage');
			if (result.newStock != null) {
				$('#itemDetailsTotalStock').val(result.newStock);
			}

			if (result && result.success !== undefined) {
				wasSuccessful = !!result.success;
			} else {
				var resultText = (result.alertMessage || result.message || '').toLowerCase();
				wasSuccessful = resultText.indexOf('success') !== -1 || resultText.indexOf('updated') !== -1;
			}
		},
		error: function () {
			showToastMessage('Unable to update item right now.', 'itemDetailsMessage');
		},
		complete: function () {
			isUpdateItemSubmitInProgress = false;
			$updateItemButton.prop('disabled', false).text(originalButtonText);

			if (wasSuccessful) {
				var itemForm = $updateItemButton.closest('form').get(0);
				if (itemForm) {
					itemForm.reset();
				}
				$('#itemDetailsUnitAsSold').trigger('chosen:updated');
				$('#itemDetailsStatus').trigger('chosen:updated');
				$('#imageContainer').empty();
			}

			searchTableCreator('itemDetailsTableDiv', itemDetailsSearchTableCreatorFile, 'itemDetailsTable');
			searchTableCreator('purchaseDetailsTableDiv', purchaseDetailsSearchTableCreatorFile, 'purchaseDetailsTable');
			searchTableCreator('saleDetailsTableDiv', saleDetailsSearchTableCreatorFile, 'saleDetailsTable');
			reportsTableCreator('itemReportsTableDiv', itemReportsSearchTableCreatorFile, 'itemReportsTable');
			reportsPurchaseTableCreator('purchaseReportsTableDiv', purchaseReportsSearchTableCreatorFile, 'purchaseReportsTable');
			reportsSaleTableCreator('saleReportsTableDiv', saleReportsSearchTableCreatorFile, 'saleReportsTable');
		}
	});
}


// Function to call the upateCustomerDetails.php script to UPDATE customer data in db
function updateCustomer() {
	var customerDetailsCustomerID = $('#customerDetailsCustomerID').val();
	var customerDetailsCustomerFullName = $('#customerDetailsCustomerFullName').val();
	var customerDetailsCustomerMobile = $('#customerDetailsCustomerMobile').val();
	var customerDetailsCustomerPhone2 = $('#customerDetailsCustomerPhone2').val();
	var customerDetailsCustomerAddress = $('#customerDetailsCustomerAddress').val();
	var customerDetailsCustomerEmail = $('#customerDetailsCustomerEmail').val();
	var customerDetailsCustomerAddress2 = $('#customerDetailsCustomerAddress2').val();
	var customerDetailsCustomerCity = $('#customerDetailsCustomerCity').val();
	var customerDetailsCustomerDistrict = $('#customerDetailsCustomerDistrict').val();
	var customerDetailsStatus = $('#customerDetailsStatus option:selected').text();

	$.ajax({
		url: 'model/customer/updateCustomerDetails.php',
		method: 'POST',
		data: {
			customerDetailsCustomerID: customerDetailsCustomerID,
			customerDetailsCustomerFullName: customerDetailsCustomerFullName,
			customerDetailsCustomerMobile: customerDetailsCustomerMobile,
			customerDetailsCustomerPhone2: customerDetailsCustomerPhone2,
			customerDetailsCustomerAddress: customerDetailsCustomerAddress,
			customerDetailsCustomerEmail: customerDetailsCustomerEmail,
			customerDetailsCustomerAddress2: customerDetailsCustomerAddress2,
			customerDetailsCustomerCity: customerDetailsCustomerCity,
			customerDetailsCustomerDistrict: customerDetailsCustomerDistrict,
			customerDetailsStatus: customerDetailsStatus,
		},
		success: function (data) {
			showToastMessage(data, 'customerDetailsMessage');
		},
		complete: function () {
			searchTableCreator('customerDetailsTableDiv', customerDetailsSearchTableCreatorFile, 'customerDetailsTable');
			reportsTableCreator('customerReportsTableDiv', customerReportsSearchTableCreatorFile, 'customerReportsTable');
			searchTableCreator('saleDetailsTableDiv', saleDetailsSearchTableCreatorFile, 'saleDetailsTable');
			reportsSaleTableCreator('saleReportsTableDiv', saleReportsSearchTableCreatorFile, 'saleReportsTable');
		}
	});
}


// Function to call the upateVendorDetails.php script to UPDATE vendor data in db
function updateVendor() {
	var vendorDetailsVendorID = $('#vendorDetailsVendorID').val();
	var vendorDetailsVendorFullName = $('#vendorDetailsVendorFullName').val();
	var vendorDetailsVendorMobile = $('#vendorDetailsVendorMobile').val();
	var vendorDetailsVendorPhone2 = $('#vendorDetailsVendorPhone2').val();
	var vendorDetailsVendorAddress = $('#vendorDetailsVendorAddress').val();
	var vendorDetailsVendorEmail = $('#vendorDetailsVendorEmail').val();
	var vendorDetailsVendorAddress2 = $('#vendorDetailsVendorAddress2').val();
	var vendorDetailsVendorCity = $('#vendorDetailsVendorCity').val();
	var vendorDetailsVendorDistrict = $('#vendorDetailsVendorDistrict').val();
	var vendorDetailsStatus = $('#vendorDetailsStatus option:selected').text();

	$.ajax({
		url: 'model/vendor/updateVendorDetails.php',
		method: 'POST',
		data: {
			vendorDetailsVendorID: vendorDetailsVendorID,
			vendorDetailsVendorFullName: vendorDetailsVendorFullName,
			vendorDetailsVendorMobile: vendorDetailsVendorMobile,
			vendorDetailsVendorPhone2: vendorDetailsVendorPhone2,
			vendorDetailsVendorAddress: vendorDetailsVendorAddress,
			vendorDetailsVendorEmail: vendorDetailsVendorEmail,
			vendorDetailsVendorAddress2: vendorDetailsVendorAddress2,
			vendorDetailsVendorCity: vendorDetailsVendorCity,
			vendorDetailsVendorDistrict: vendorDetailsVendorDistrict,
			vendorDetailsStatus: vendorDetailsStatus,
		},
		success: function (data) {
			showToastMessage(data, 'vendorDetailsMessage');
		},
		complete: function () {
			searchTableCreator('purchaseDetailsTableDiv', purchaseDetailsSearchTableCreatorFile, 'purchaseDetailsTable');
			searchTableCreator('vendorDetailsTableDiv', vendorDetailsSearchTableCreatorFile, 'vendorDetailsTable');
			reportsPurchaseTableCreator('purchaseReportsTableDiv', purchaseReportsSearchTableCreatorFile, 'purchaseReportsTable');
			reportsTableCreator('vendorReportsTableDiv', vendorReportsSearchTableCreatorFile, 'vendorReportsTable');
		}
	});
}


// Function to call the updatePurchase.php script to update purchase data to db
function updatePurchase() {
	var purchaseDetailsPurchaseDate = $('#purchaseDetailsPurchaseDate').val();
	var purchaseDetailsPurchaseID = $('#purchaseDetailsPurchaseID').val();
	var purchaseDetailsVendorName = $('#purchaseDetailsVendorName').val();
	var purchaseItems = [];
	$('.purchase-item-row').each(function () {
		var row = $(this);
		purchaseItems.push({
			itemNumber: row.find('.purchase-item-number').val(),
			itemName: row.find('.purchase-item-name').val(),
			quantity: row.find('.purchase-item-quantity').val(),
			unitPrice: row.find('.purchase-item-unit-price').val()
		});
	});

	$.ajax({
		url: 'model/purchase/updatePurchase.php',
		method: 'POST',
		data: {
			purchaseDetailsPurchaseDate: purchaseDetailsPurchaseDate,
			purchaseDetailsPurchaseID: purchaseDetailsPurchaseID,
			purchaseDetailsVendorName: purchaseDetailsVendorName,
			purchaseItems: JSON.stringify(purchaseItems)
		},
		success: function (data) {
			var result = typeof data === 'string' ? $.parseJSON(data) : data;
			showToastMessage((result && result.message) || 'Purchase transaction update failed.', 'purchaseDetailsMessage');
		},
		complete: function () {
			getItemStockToPopulate('purchaseDetailsItemNumber', getItemStockFile, 'purchaseDetailsCurrentStock');
			searchTableCreator('purchaseDetailsTableDiv', purchaseDetailsSearchTableCreatorFile, 'purchaseDetailsTable');
			reportsPurchaseTableCreator('purchaseReportsTableDiv', purchaseReportsSearchTableCreatorFile, 'purchaseReportsTable');
			searchTableCreator('itemDetailsTableDiv', itemDetailsSearchTableCreatorFile, 'itemDetailsTable');
			reportsTableCreator('itemReportsTableDiv', itemReportsSearchTableCreatorFile, 'itemReportsTable');
		}
	});
}


// Function to call the updateSale.php script to update sale data to db
function viewCreditBook() {
	var customerID = $('#creditCustomerID').val();
	if (customerID === '') {
		showToastMessage('Please enter a customer ID.', 'creditBookMessage');
		return;
	}

	$.ajax({
		url: 'model/customer/getCustomerCreditBook.php',
		method: 'POST',
		data: { customerID: customerID },
		success: function (data) {
			try {
				var result = $.parseJSON(data);
				if (result.success) {
					$('#creditCustomerName').val(result.customerName);
					$('#creditOutstandingBalance').val(result.outstandingBalance);
					var rows = '';
					if (result.ledger.length > 0) {
						$.each(result.ledger, function (index, row) {
							rows += '<tr><td>' + row.entryDate + '</td><td>' + row.entryType + '</td><td>' + row.amount + '</td><td>' + row.balanceAfter + '</td><td>' + (row.note || '') + '</td></tr>';
						});
					} else {
						rows = '<tr><td colspan="5" class="text-muted">No ledger entries yet.</td></tr>';
					}
					$('#creditLedgerBody').html(rows);
				} else {
					showToastMessage(result.message, 'creditBookMessage');
				}
			} catch (e) {
				showToastMessage('Unable to load credit book.', 'creditBookMessage');
			}
		}
	});
}

function recordCustomerPayment() {
	if (isCreditPaymentSubmitInProgress) {
		showToastMessage('Payment is already being recorded. Please wait.', 'creditBookMessage');
		return;
	}

	var customerID = $('#creditCustomerID').val();
	var paymentAmount = $('#creditPaymentAmount').val();
	var paymentDate = $('#creditPaymentDate').val();
	var paymentMethod = $('#creditPaymentMethod').val();
	var referenceNumber = $('#creditReferenceNumber').val();
	var note = $('#creditNote').val();
	var transactionID = $.trim($('#creditTransactionID').val());
	var $recordPaymentButton = $('#recordPaymentButton');
	var originalButtonText = $recordPaymentButton.data('original-text') || $recordPaymentButton.text();
	$recordPaymentButton.data('original-text', originalButtonText);
	isCreditPaymentSubmitInProgress = true;
	$recordPaymentButton.prop('disabled', true).text('Saving...');

	if (customerID === '' || paymentAmount === '' || paymentAmount <= 0) {
		isCreditPaymentSubmitInProgress = false;
		$recordPaymentButton.prop('disabled', false).text(originalButtonText);
		showToastMessage('Please enter a customer ID and a valid payment amount.', 'creditBookMessage');
		return;
	}

	if (transactionID !== '' && transactionID.indexOf('TXN-') !== 0) {
		isCreditPaymentSubmitInProgress = false;
		$recordPaymentButton.prop('disabled', false).text(originalButtonText);
		showToastMessage('Please enter a valid Transaction ID (TXN-...).', 'creditBookMessage');
		return;
	}

	$.ajax({
		url: 'model/customer/recordCustomerPayment.php',
		method: 'POST',
		data: {
			customerID: customerID,
			paymentAmount: paymentAmount,
			paymentDate: paymentDate,
			paymentMethod: paymentMethod,
			referenceNumber: referenceNumber,
			note: note,
			transactionID: transactionID,
			receiptNumber: 'RC-' + customerID + '-' + Date.now()
		},
		success: function (data) {
			try {
				var result = typeof data === 'string' ? $.parseJSON(data) : data;
				showToastMessage(result.message, 'creditBookMessage');
				if (result.success) {
					$('#creditPaymentAmount').val('0');
					$('#creditTransactionID').val('');
					$('#creditReferenceNumber').val('');
					$('#creditNote').val('');
					viewCreditBook();
				}
			} catch (e) {
				showToastMessage('Unable to record payment.', 'creditBookMessage');
			}
		},
		error: function () {
			showToastMessage('Unable to record payment.', 'creditBookMessage');
		},
		complete: function () {
			isCreditPaymentSubmitInProgress = false;
			$recordPaymentButton.prop('disabled', false).text(originalButtonText);
		}
	});
}

function printCustomerReceipt() {
	var transactionID = $.trim($('#creditTransactionID').val());
	if (transactionID === '') {
		bootbox.prompt('Enter Transaction ID to reprint receipt (e.g. TXN-...)', function (result) {
			if (result !== null) {
				openSaleReceiptByTransactionID(result, 'creditBookMessage');
			}
		});
		return;
	}

	openSaleReceiptByTransactionID(transactionID, 'creditBookMessage');
}

function printSaleReceipt() {
	var transactionID = $('#saleDetailsSaleID').val();
	if (transactionID === '' || transactionID === 'Auto-generated after save') {
		bootbox.prompt('Enter Transaction ID to reprint receipt (e.g. TXN-...)', function (result) {
			if (result !== null) {
				openSaleReceiptByTransactionID(result, 'saleDetailsMessage');
			}
		});
		return;
	}

	openSaleReceiptByTransactionID(transactionID, 'saleDetailsMessage');
}

function openSaleReceiptByTransactionID(transactionID, messageDivID) {
	var cleanedTransactionID = $.trim(transactionID);
	if (cleanedTransactionID === '' || cleanedTransactionID.indexOf('TXN-') !== 0) {
		showToastMessage('Please enter a valid Transaction ID (TXN-...).', messageDivID);
		return;
	}

	window.open('model/sale/printReceipt.php?saleReference=' + encodeURIComponent(cleanedTransactionID), '_blank');
}

function updateSale() {
	var saleDetailsSaleDate = $('#saleDetailsSaleDate').val();
	var saleDetailsSaleID = $('#saleDetailsSaleID').val();
	var saleDetailsCustomerName = $('#saleDetailsCustomerName').val();
	var saleDetailsCustomerID = $('#saleDetailsCustomerID').val();
	var saleDetailsAmountPaid = $('#saleDetailsAmountPaid').val();
	var saleItems = [];
	$('.sale-item-row').each(function () {
		var row = $(this);
		saleItems.push({
			itemNumber: row.find('.sale-item-number').val(),
			itemName: row.find('.sale-item-name').val(),
			quantity: row.find('.sale-item-quantity').val(),
			unitPrice: row.find('.sale-item-unit-price').val(),
			discount: row.find('.sale-item-discount').val(),
			reason: row.find('.sale-item-reason').val()
		});
	});

	$.ajax({
		url: 'model/sale/updateSale.php',
		method: 'POST',
		data: {
			saleDetailsSaleDate: saleDetailsSaleDate,
			saleDetailsSaleID: saleDetailsSaleID,
			saleDetailsCustomerName: saleDetailsCustomerName,
			saleDetailsCustomerID: saleDetailsCustomerID,
			saleDetailsAmountPaid: saleDetailsAmountPaid,
			saleItems: JSON.stringify(saleItems)
		},
		success: function (data) {
			var result = typeof data === 'string' ? $.parseJSON(data) : data;
			showToastMessage((result && result.message) || 'Sale transaction update failed.', 'saleDetailsMessage');
		},
		complete: function () {
			getItemStockToPopulate('saleDetailsItemNumber', getItemStockFile, 'saleDetailsTotalStock');
			searchTableCreator('saleDetailsTableDiv', saleDetailsSearchTableCreatorFile, 'saleDetailsTable');
			reportsSaleTableCreator('saleReportsTableDiv', saleReportsSearchTableCreatorFile, 'saleReportsTable');
			searchTableCreator('itemDetailsTableDiv', itemDetailsSearchTableCreatorFile, 'itemDetailsTable');
			reportsTableCreator('itemReportsTableDiv', itemReportsSearchTableCreatorFile, 'itemReportsTable');
		}
	});
}