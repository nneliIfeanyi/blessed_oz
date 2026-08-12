<?php
session_start();
// Redirect the user to login page if he is not logged in.
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
$isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';

$accessNotice = '';
if (isset($_GET['accessDenied'])) {
	$deniedArea = trim((string) $_GET['accessDenied']);
	if ($deniedArea === 'settings') {
		$accessNotice = 'Access restricted: Settings is available to super admin only.';
	} elseif ($deniedArea === 'dashboard') {
		$accessNotice = 'Access restricted: Dashboard is available to super admin only.';
	}
}

if (!$isSuperAdmin && $accessNotice === '') {
	$accessNotice = 'Dashboard and Settings are restricted to super admin accounts.';
}

$settingsFile = 'inc/config/site_settings.json';
$settings = [
	'siteName' => 'Inventory System',
	'lowStockThreshold' => 5,
	'enableProductDescription' => true,
	'enableProductImage' => true
];
if (file_exists($settingsFile)) {
	$json = file_get_contents($settingsFile);
	if ($json !== false) {
		$decoded = json_decode($json, true);
		if (is_array($decoded)) {
			$settings = array_merge($settings, $decoded);
		}
	}
}

function formatCurrency($value)
{
	return '₦' . number_format((float) $value, 2);
}

$dashboardSummary = array(
	'currentStockValue' => 0,
	'totalSales' => 0,
	'totalPurchases' => 0,
	'totalCredits' => 0
);
$dashboardMovements = array();
$dashboardMovementError = '';

try {
	$dashboardStockValueStatement = $conn->prepare('SELECT COALESCE(SUM(stock * unitPrice), 0) FROM item WHERE storeID = :storeID');
	$dashboardStockValueStatement->execute(['storeID' => $activeStoreID]);
	$dashboardSummary['currentStockValue'] = (float) $dashboardStockValueStatement->fetchColumn();

	$dashboardSalesStatement = $conn->prepare('SELECT COALESCE(SUM(lineTotal), 0) FROM sale_items WHERE storeID = :storeID');
	$dashboardSalesStatement->execute(['storeID' => $activeStoreID]);
	$dashboardSummary['totalSales'] = (float) $dashboardSalesStatement->fetchColumn();

	$dashboardPurchasesStatement = $conn->prepare('SELECT COALESCE(SUM(quantity * unitPrice), 0) FROM purchase WHERE storeID = :storeID');
	$dashboardPurchasesStatement->execute(['storeID' => $activeStoreID]);
	$dashboardSummary['totalPurchases'] = (float) $dashboardPurchasesStatement->fetchColumn();

	$dashboardCreditsStatement = $conn->prepare('SELECT COALESCE(SUM(outstandingBalance), 0) FROM (
		SELECT ROUND(GREATEST(COALESCE(SUM(si.lineTotal), 0) - COALESCE(sh.amountPaid, 0), 0), 2) AS outstandingBalance
		FROM sale_headers sh
		LEFT JOIN sale_items si ON si.saleReference = sh.saleReference AND si.storeID = sh.storeID
		WHERE sh.storeID = :storeID
		GROUP BY sh.id, sh.amountPaid
	) AS creditTotals');
	$dashboardCreditsStatement->execute(['storeID' => $activeStoreID]);
	$dashboardSummary['totalCredits'] = (float) $dashboardCreditsStatement->fetchColumn();

	$dashboardMovementSql = 'SELECT movementType, movementDate, itemNumber, itemName, quantity, unitPrice, referenceName, reason, direction FROM (
		SELECT "Purchase" AS movementType, purchaseDate AS movementDate, itemNumber, itemName, quantity, unitPrice, vendorName AS referenceName, "" AS reason, "In" AS direction, purchaseID AS movementSequence FROM purchase WHERE storeID = :purchaseStoreID
		UNION ALL
		SELECT "Stock Out" AS movementType, sh.saleDate AS movementDate, si.itemNumber, si.itemName, si.quantity, si.unitPrice, COALESCE(sh.customerName, "") AS referenceName, COALESCE(si.reason, "Sales") AS reason, "Out" AS direction, si.saleItemID AS movementSequence
		FROM sale_items si
		LEFT JOIN sale_headers sh ON sh.saleReference = si.saleReference
		WHERE si.storeID = :saleItemsStoreID
		UNION ALL
		SELECT "Stock Out" AS movementType, saleDate AS movementDate, itemNumber, itemName, quantity, unitPrice, customerName AS referenceName, reason, "Out" AS direction, saleID AS movementSequence FROM sale WHERE storeID = :saleStoreID
	) AS movementLog
	ORDER BY movementDate DESC, movementSequence DESC
	';
	$dashboardMovementStatement = $conn->prepare($dashboardMovementSql);
	$dashboardMovementStatement->execute(['purchaseStoreID' => $activeStoreID, 'saleItemsStoreID' => $activeStoreID, 'saleStoreID' => $activeStoreID]);
	$dashboardMovements = $dashboardMovementStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$dashboardMovementError = 'Dashboard data is unavailable right now.';
}
?>

<body>
	<?php
	require 'inc/navigation.php';
	?>
	<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>
	<?php if ($accessNotice !== '') { ?>
		<div class="container-fluid mt-3">
			<div class="alert alert-info mb-0" role="alert"><?php echo htmlspecialchars($accessNotice); ?></div>
		</div>
	<?php } ?>

	<!-- Page Content -->
	<div class="container-fluid">
		<div class="row">
			<div class="col-lg-2 sidebar-column">
				<h1 class="my-4"></h1>
				<div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
					<?php if ($isSuperAdmin) { ?>
						<a class="nav-link active" id="v-pills-dashboard-tab" data-toggle="pill" href="#v-pills-dashboard" role="tab" aria-controls="v-pills-dashboard" aria-selected="true">Dashboard</a>
						<a class="nav-link" id="v-pills-item-tab" data-toggle="pill" href="#v-pills-item" role="tab" aria-controls="v-pills-item" aria-selected="false">Item</a>
					<?php } else { ?>
						<a class="nav-link active" id="v-pills-item-tab" data-toggle="pill" href="#v-pills-item" role="tab" aria-controls="v-pills-item" aria-selected="true">Item</a>
					<?php } ?>
					<a class="nav-link" id="v-pills-purchase-tab" data-toggle="pill" href="#v-pills-purchase" role="tab" aria-controls="v-pills-purchase" aria-selected="false">Purchase</a>
					<a class="nav-link" id="v-pills-vendor-tab" data-toggle="pill" href="#v-pills-vendor" role="tab" aria-controls="v-pills-vendor" aria-selected="false">Vendor</a>
					<a class="nav-link" id="v-pills-sale-tab" data-toggle="pill" href="#v-pills-sale" role="tab" aria-controls="v-pills-sale" aria-selected="false">Sale</a>
					<a class="nav-link" id="v-pills-customer-tab" data-toggle="pill" href="#v-pills-customer" role="tab" aria-controls="v-pills-customer" aria-selected="false">Customer</a>
					<a class="nav-link" id="v-pills-credit-tab" data-toggle="pill" href="#v-pills-credit" role="tab" aria-controls="v-pills-credit" aria-selected="false">Credit Book</a>
					<a class="nav-link" id="v-pills-search-tab" data-toggle="pill" href="#v-pills-search" role="tab" aria-controls="v-pills-search" aria-selected="false">Search</a>
					<a class="nav-link" id="v-pills-reports-tab" data-toggle="pill" href="#v-pills-reports" role="tab" aria-controls="v-pills-reports" aria-selected="false">Reports</a>
				</div>
			</div>
			<div class="col-lg-10">
				<div class="tab-content" id="v-pills-tabContent">
					<?php if ($isSuperAdmin) { ?>
						<div class="tab-pane fade show active" id="v-pills-dashboard" role="tabpanel" aria-labelledby="v-pills-dashboard-tab">
							<div class="card card-outline-secondary my-4">
								<div class="card-header d-flex justify-content-between align-items-center">
									<span>Dashboard</span>
									<button type="button" id="refreshDashboardBtn" class="btn btn-sm btn-outline-primary">Refresh</button>
								</div>
								<div class="card-body">
									<?php if ($dashboardMovementError != '') {
										echo '<div class="alert alert-warning">' . htmlspecialchars($dashboardMovementError) . '</div>';
									} ?>
									<div class="row">
										<div class="col-md-3 mb-3">
											<div class="dashboard-card dashboard-card-primary">
												<div class="dashboard-label">Current Stock Value</div>
												<div class="dashboard-value" id="dashboardCurrentStockValue"><?php echo htmlspecialchars(formatCurrency($dashboardSummary['currentStockValue'])); ?></div>
											</div>
										</div>
										<div class="col-md-3 mb-3">
											<div class="dashboard-card dashboard-card-success">
												<div class="dashboard-label">Total Sales</div>
												<div class="dashboard-value" id="dashboardTotalSales"><?php echo htmlspecialchars(formatCurrency($dashboardSummary['totalSales'])); ?></div>
											</div>
										</div>
										<div class="col-md-3 mb-3">
											<div class="dashboard-card dashboard-card-warning">
												<div class="dashboard-label">Total Purchases</div>
												<div class="dashboard-value" id="dashboardTotalPurchases"><?php echo htmlspecialchars(formatCurrency($dashboardSummary['totalPurchases'])); ?></div>
											</div>
										</div>
										<div class="col-md-3 mb-3">
											<div class="dashboard-card dashboard-card-danger">
												<div class="dashboard-label">Total Credits</div>
												<div class="dashboard-value" id="dashboardTotalCredits"><?php echo htmlspecialchars(formatCurrency($dashboardSummary['totalCredits'])); ?></div>
											</div>
										</div>
									</div>
									<h6 class="mb-3">Stock Movement</h6>
									<div class="table-responsive">
										<table id="dashboardRecentMovementsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
											<thead>
												<tr>
													<th>Date</th>
													<th>Item</th>
													<th>Qty</th>
													<th>Direction</th>
													<th>Reference</th>
													<th>Reason</th>
												</tr>
											</thead>
											<tbody>
												<?php if (count($dashboardMovements) > 0) {
													foreach ($dashboardMovements as $movement) {
														echo '<tr>' .
															'<td>' . htmlspecialchars($movement['movementDate']) . '</td>' .
															'<td>' . htmlspecialchars($movement['itemName']) . ' (' . htmlspecialchars($movement['itemNumber']) . ')</td>' .
															'<td>' . htmlspecialchars($movement['quantity']) . '</td>' .
															'<td>' . htmlspecialchars($movement['direction']) . '</td>' .
															'<td>' . htmlspecialchars($movement['referenceName']) . '</td>' .
															'<td>' . htmlspecialchars($movement['reason']) . '</td>' .
															'</tr>';
													}
												} ?>
											</tbody>
										</table>
									</div>
								</div>
							</div>
						</div>
					<?php } ?>
					<div class="tab-pane fade<?php echo !$isSuperAdmin ? ' show active' : ''; ?>" id="v-pills-item" role="tabpanel" aria-labelledby="v-pills-item-tab">
						<div class="card card-outline-secondary my-4">
							<div class="card-header">Item Details</div>
							<div class="card-body">
								<ul class="nav nav-tabs" role="tablist">
									<li class="nav-item">
										<a class="nav-link active" data-toggle="tab" href="#itemDetailsTab">Item</a>
									</li>
									<?php if (!empty($settings['enableProductImage'])) { ?>
										<li class="nav-item">
											<a class="nav-link" data-toggle="tab" href="#itemImageTab">Upload Image</a>
										</li>
									<?php } ?>
								</ul>

								<!-- Tab panes for item details and image sections -->
								<div class="tab-content">
									<div id="itemDetailsTab" class="container-fluid tab-pane active">
										<br>
										<!-- Div to show the ajax message from validations/db submission -->
										<div id="itemDetailsMessage"></div>
										<form>
											<div class="form-row compact-form-row align-items-end">
												<div class="form-group col-lg-2 col-md-4" style="display:inline-block">
													<label for="itemDetailsItemNumber">Item Number<span class="requiredIcon">*</span></label>
													<input type="text" class="form-control" name="itemDetailsItemNumber" id="itemDetailsItemNumber" autocomplete="off">
													<div id="itemDetailsItemNumberSuggestionsDiv" class="customListDivWidth"></div>
												</div>
												<div class="form-group col-lg-2 col-md-4">
													<label for="itemDetailsProductID">Product ID</label>
													<input class="form-control invTooltip" type="number" readonly id="itemDetailsProductID" name="itemDetailsProductID" title="This will be auto-generated when you add a new item">
												</div>
												<div class="form-group col-lg-4 col-md-8">
													<label for="itemDetailsItemName">Item Name<span class="requiredIcon">*</span></label>
													<input type="text" class="form-control" name="itemDetailsItemName" id="itemDetailsItemName" autocomplete="off">
													<div id="itemDetailsItemNameSuggestionsDiv" class="customListDivWidth"></div>
												</div>
												<div class="form-group col-lg-2 col-md-4">
													<label for="itemDetailsUnitAsSold">Unit Sold As</label>
													<select class="form-control chosenSelect" name="itemDetailsUnitAsSold" id="itemDetailsUnitAsSold">
														<option value="pcs">pcs</option>
														<option value="carton">carton</option>
														<option value="pack">pack</option>
														<option value="roll">roll</option>
														<option value="kg">kg</option>
														<option value="g">g</option>
														<option value="litre">litre</option>
														<option value="ml">ml</option>
														<option value="box">box</option>
														<option value="dozen">dozen</option>
														<option value="pair">pair</option>
														<option value="bundle">bundle</option>
														<option value="sachet">sachet</option>
														<option value="meter">meter</option>
														<option value="yard">yard</option>
													</select>
												</div>
												<div class="form-group col-lg-2 col-md-4">
													<label for="itemDetailsStatus">Status</label>
													<select id="itemDetailsStatus" name="itemDetailsStatus" class="form-control chosenSelect">
														<?php include('inc/statusList.html'); ?>
													</select>
												</div>
											</div>
											<div class="form-row compact-form-row align-items-end">
												<div class="form-group col-lg-2 col-md-4">
													<label for="itemDetailsDiscount">Discount %</label>
													<input type="text" class="form-control" value="0" name="itemDetailsDiscount" id="itemDetailsDiscount">
												</div>
												<div class="form-group col-lg-2 col-md-4">
													<label for="itemDetailsQuantity">Initial Stock</label>
													<input type="number" class="form-control" value="0" name="itemDetailsQuantity" id="itemDetailsQuantity" title="Used only when creating a new item">
												</div>
												<div class="form-group col-lg-2 col-md-4">
													<label for="itemDetailsUnitPrice">Unit Price<span class="requiredIcon">*</span></label>
													<input type="text" class="form-control" value="0" name="itemDetailsUnitPrice" id="itemDetailsUnitPrice">
												</div>
												<div class="form-group col-lg-2 col-md-4">
													<label for="itemDetailsTotalStock">Current Stock</label>
													<input type="text" class="form-control" name="itemDetailsTotalStock" id="itemDetailsTotalStock" readonly>
												</div>
												<div class="form-group col-lg-3 col-md-8 compact-image-slot">
													<div id="imageContainer"></div>
												</div>
											</div>
											<div class="form-row">
												<div class="form-group col-lg-9 col-md-12" style="display:inline-block">
													<?php if (!empty($settings['enableProductDescription'])) { ?>
														<textarea rows="4" class="form-control" placeholder="Description" name="itemDetailsDescription" id="itemDetailsDescription"></textarea>
													<?php } ?>
												</div>
											</div>
											<button type="button" id="addItem" class="btn btn-success">Add Item</button>
											<button type="button" id="updateItemDetailsButton" class="btn btn-primary">Update</button>
											<button type="button" id="deleteItem" class="btn btn-danger">Delete</button>
											<button type="reset" class="btn" id="itemClear">Clear</button>
										</form>
									</div>
									<?php if (!empty($settings['enableProductImage'])) { ?>
										<div id="itemImageTab" class="container-fluid tab-pane fade">
											<br>
											<div id="itemImageMessage"></div>
											<p>You can upload an image for a particular item using this section.</p>
											<p>Please make sure the item is already added to database before uploading the image.</p>
											<br>
											<form name="imageForm" id="imageForm" method="post">
												<div class="form-row">
													<div class="form-group col-md-3" style="display:inline-block">
														<label for="itemImageItemNumber">Item Number<span class="requiredIcon">*</span></label>
														<input type="text" class="form-control" name="itemImageItemNumber" id="itemImageItemNumber" autocomplete="off">
														<div id="itemImageItemNumberSuggestionsDiv" class="customListDivWidth"></div>
													</div>
													<div class="form-group col-md-4">
														<label for="itemImageItemName">Item Name</label>
														<input type="text" class="form-control" name="itemImageItemName" id="itemImageItemName" readonly>
													</div>
												</div>
												<br>
												<div class="form-row">
													<div class="form-group col-md-7">
														<label for="itemImageFile">Select Image ( <span class="blueText">jpg</span>, <span class="blueText">jpeg</span>, <span class="blueText">gif</span>, <span class="blueText">png</span> only )</label>
														<input type="file" class="form-control-file btn btn-dark" id="itemImageFile" name="itemImageFile">
													</div>
												</div>
												<br>
												<button type="button" id="updateImageButton" class="btn btn-primary">Upload Image</button>
												<button type="button" id="deleteImageButton" class="btn btn-danger">Delete Image</button>
												<button type="reset" class="btn">Clear</button>
											</form>
										</div>
									<?php } ?>
								</div>
							</div>
						</div>
					</div>
					<div class="tab-pane fade" id="v-pills-purchase" role="tabpanel" aria-labelledby="v-pills-purchase-tab">
						<div class="card card-outline-secondary my-4">
							<div class="card-header">Purchase Details</div>
							<div class="card-body">
								<div id="purchaseDetailsMessage"></div>
								<form>
									<div class="form-row">
										<div class="form-group col-md-3">
											<label for="purchaseDetailsPurchaseDate">Purchase Date<span class="requiredIcon">*</span></label>
											<input type="text" class="form-control datepicker" id="purchaseDetailsPurchaseDate" name="purchaseDetailsPurchaseDate" readonly value="<?php echo date('Y-m-d'); ?>">
										</div>
										<div class="form-group col-md-3">
											<label for="purchaseDetailsPurchaseID">Transaction ID</label>
											<input type="text" class="form-control invTooltip" id="purchaseDetailsPurchaseID" name="purchaseDetailsPurchaseID" value="Auto-generated after save" title="This will be auto-generated when you save a transaction" autocomplete="off" readonly>
											<div id="purchaseDetailsPurchaseIDSuggestionsDiv" class="customListDivWidth"></div>
										</div>
										<div class="form-group col-md-4">
											<label for="purchaseDetailsVendorName">Vendor Name<span class="requiredIcon">*</span></label>
											<select id="purchaseDetailsVendorName" name="purchaseDetailsVendorName" class="form-control chosenSelect">
												<?php
												require('model/vendor/getVendorNames.php');
												?>
											</select>
										</div>
									</div>
									<div id="purchaseItemsContainer"></div>
									<div class="form-group mt-3">
										<button type="button" id="addPurchaseItemRowButton" class="btn btn-outline-primary btn-sm">Add Item</button>
										<button type="button" id="addPurchase" class="btn btn-success">Add Purchase</button>
										<button type="button" id="updatePurchaseDetailsButton" class="btn btn-primary">Update</button>
										<button type="reset" class="btn">Clear</button>
									</div>
								</form>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="v-pills-vendor" role="tabpanel" aria-labelledby="v-pills-vendor-tab">
						<div class="card card-outline-secondary my-4">
							<div class="card-header">Vendor Details</div>
							<div class="card-body">
								<!-- Div to show the ajax message from validations/db submission -->
								<div id="vendorDetailsMessage"></div>
								<form>
									<div class="form-row">
										<div class="form-group col-md-6">
											<label for="vendorDetailsVendorFullName">Full Name<span class="requiredIcon">*</span></label>
											<input type="text" class="form-control" id="vendorDetailsVendorFullName" name="vendorDetailsVendorFullName" placeholder="">
										</div>
										<div class="form-group col-md-2">
											<label for="vendorDetailsStatus">Status</label>
											<select id="vendorDetailsStatus" name="vendorDetailsStatus" class="form-control chosenSelect">
												<?php include('inc/statusList.html'); ?>
											</select>
										</div>
										<div class="form-group col-md-3">
											<label for="vendorDetailsVendorID">Vendor ID</label>
											<input type="text" class="form-control invTooltip" id="vendorDetailsVendorID" name="vendorDetailsVendorID" title="This will be auto-generated when you add a new vendor" autocomplete="off">
											<div id="vendorDetailsVendorIDSuggestionsDiv" class="customListDivWidth"></div>
										</div>
									</div>
									<div class="form-row">
										<div class="form-group col-md-6">
											<label for="vendorDetailsVendorMobile">Phone (mobile)<span class="requiredIcon">*</span></label>
											<input type="text" class="form-control invTooltip" id="vendorDetailsVendorMobile" name="vendorDetailsVendorMobile" title="Do not enter leading 0">
										</div>
										<div class="form-group col-md-6">
											<label for="vendorDetailsVendorEmail">Email</label>
											<input type="email" class="form-control" id="vendorDetailsVendorEmail" name="vendorDetailsVendorEmail">
										</div>
									</div>
									<div class="form-group">
										<label for="vendorDetailsVendorAddress">Address<span class="requiredIcon">*</span></label>
										<input type="text" class="form-control" id="vendorDetailsVendorAddress" name="vendorDetailsVendorAddress">
									</div>
									<div class="form-row">
										<div class="form-group col-md-8">
											<label for="vendorDetailsVendorDistrict">District</label>
											<select id="vendorDetailsVendorDistrict" name="vendorDetailsVendorDistrict" class="form-control chosenSelect">
												<?php include('inc/districtList.html'); ?>
											</select>
										</div>
									</div>
									<button type="button" id="addVendor" name="addVendor" class="btn btn-success">Add Vendor</button>
									<button type="button" id="updateVendorDetailsButton" class="btn btn-primary">Update</button>
									<button type="button" id="deleteVendorButton" class="btn btn-danger">Delete</button>
									<button type="reset" class="btn">Clear</button>
								</form>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="v-pills-sale" role="tabpanel" aria-labelledby="v-pills-sale-tab">
						<div class="card card-outline-secondary my-4">
							<div class="card-header">Stock Out Details</div>
							<div class="card-body">
								<div id="saleDetailsMessage"></div>
								<form>
									<input type="hidden" id="saleDetailsSaleID" name="saleDetailsSaleID" value="">
									<div class="form-row compact-form-row align-items-end">
										<div class="form-group col-lg-2 col-md-4">
											<label for="saleDetailsCustomerID">Customer ID<span class="requiredIcon">*</span></label>
											<input type="text" class="form-control" id="saleDetailsCustomerID" name="saleDetailsCustomerID" autocomplete="off">
											<div id="saleDetailsCustomerIDSuggestionsDiv" class="customListDivWidth"></div>
										</div>
										<div class="form-group col-lg-3 col-md-8">
											<label for="saleDetailsCustomerName">Customer Name</label>
											<input type="text" class="form-control" id="saleDetailsCustomerName" name="saleDetailsCustomerName" readonly>
										</div>
										<div class="form-group col-lg-3 col-md-4">
											<label for="saleDetailsSaleDate">Sale Date<span class="requiredIcon">*</span></label>
											<input type="text" class="form-control datepicker" id="saleDetailsSaleDate" value="<?php echo date('Y-m-d'); ?>" name="saleDetailsSaleDate" readonly>
										</div>
										<div class="form-group col-lg-4 col-md-4">
											<label for="saleDetailsAmountPaid">Initial Amount Paid</label>
											<input type="number" class="form-control" id="saleDetailsAmountPaid" name="saleDetailsAmountPaid" value="0">
										</div>
									</div>
									<div id="saleItemsContainer"></div>
									<div class="form-group mt-3">
										<button type="button" id="addSaleItemRowButton" class="btn btn-outline-primary btn-sm">Add Item</button>
										<button type="button" id="addSaleButton" class="btn btn-success">Stock Out</button>
										<button type="button" id="printSaleReceiptButton" class="btn btn-outline-secondary">Print Receipt</button>
										<button type="reset" id="saleClear" class="btn">Clear</button>
									</div>
								</form>
							</div>
						</div>
					</div>
					<div class="tab-pane fade" id="v-pills-credit" role="tabpanel" aria-labelledby="v-pills-credit-tab">
						<div class="card card-outline-secondary my-4">
							<div class="card-header d-flex justify-content-between align-items-center">
								<span>Customer Credit Book</span>
								<a href="creditorsList.php" class="btn btn-sm btn-outline-primary">List All</a>
							</div>
							<div class="card-body">
								<div id="creditBookMessage"></div>
								<div class="form-row">
									<div class="form-group col-md-3">
										<label for="creditCustomerID">Customer ID<span class="requiredIcon">*</span></label>
										<input type="text" class="form-control" id="creditCustomerID" name="creditCustomerID" autocomplete="off">
										<div id="creditCustomerIDSuggestionsDiv" class="customListDivWidth"></div>
									</div>
									<div class="form-group col-md-4">
										<label for="creditCustomerName">Customer Name</label>
										<input type="text" class="form-control" id="creditCustomerName" name="creditCustomerName" readonly>
									</div>
									<div class="form-group col-md-3">
										<label for="creditOutstandingBalance">Outstanding Balance</label>
										<input type="text" class="form-control" id="creditOutstandingBalance" name="creditOutstandingBalance" readonly>
									</div>
									<div class="form-group col-md-2">
										<label for="creditTransactionID">Transaction ID</label>
										<input type="text" class="form-control" id="creditTransactionID" name="creditTransactionID" placeholder="TXN-..." autocomplete="off">
										<div id="creditTransactionIDSuggestionsDiv" class="customListDivWidth"></div>
									</div>
								</div>
								<div class="form-row">
									<div class="form-group col-md-2">
										<label for="creditPaymentAmount">Payment Amount</label>
										<input type="number" class="form-control" id="creditPaymentAmount" name="creditPaymentAmount" value="0">
									</div>
									<div class="form-group col-md-2">
										<label for="creditPaymentDate">Payment Date</label>
										<input type="text" class="form-control datepicker" id="creditPaymentDate" name="creditPaymentDate" readonly value="<?php echo date('Y-m-d'); ?>">
									</div>
									<div class="form-group col-md-2">
										<label for="creditPaymentMethod">Method</label>
										<select class="form-control" id="creditPaymentMethod" name="creditPaymentMethod">
											<option value="Cash">Cash</option>
											<option value="Transfer">Transfer</option>
											<option value="POS">POS</option>
										</select>
									</div>
									<div class="form-group col-md-2">
										<label for="creditReferenceNumber">Reference</label>
										<input type="text" class="form-control" id="creditReferenceNumber" name="creditReferenceNumber">
									</div>
									<div class="form-group col-md-3">
										<label for="creditNote">Note</label>
										<input type="text" class="form-control" id="creditNote" name="creditNote">
									</div>
								</div>
								<div class="form-group">
									<button type="button" id="viewCreditBookButton" class="btn btn-primary">View Credit Book</button>
									<button type="button" id="recordPaymentButton" class="btn btn-success">Record Payment</button>
									<button type="button" id="printReceiptButton" class="btn btn-outline-secondary">Print Receipt</button>
								</div>
								<div class="table-responsive mt-3">
									<table class="table table-sm table-striped table-bordered table-hover">
										<thead>
											<tr>
												<th>Date</th>
												<th>Type</th>
												<th>Amount</th>
												<th>Balance</th>
												<th>Note</th>
											</tr>
										</thead>
										<tbody id="creditLedgerBody">
											<tr>
												<td colspan="5" class="text-muted">Select a customer to view the ledger.</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
					<div class="tab-pane fade" id="v-pills-customer" role="tabpanel" aria-labelledby="v-pills-customer-tab">
						<div class="card card-outline-secondary my-4">
							<div class="card-header">Customer Details</div>
							<div class="card-body">
								<!-- Div to show the ajax message from validations/db submission -->
								<div id="customerDetailsMessage"></div>
								<form>
									<div class="form-row compact-form-row align-items-end">
										<div class="form-group col-lg-3 col-md-6">
											<label for="customerDetailsCustomerFullName">Full Name<span class="requiredIcon">*</span></label>
											<input type="text" class="form-control" id="customerDetailsCustomerFullName" name="customerDetailsCustomerFullName">
										</div>
										<div class="form-group col-lg-2 col-md-3">
											<label for="customerDetailsStatus">Status</label>
											<select id="customerDetailsStatus" name="customerDetailsStatus" class="form-control chosenSelect">
												<?php include('inc/statusList.html'); ?>
											</select>
										</div>
										<div class="form-group col-lg-2 col-md-3">
											<label for="customerDetailsCustomerID">Customer ID</label>
											<input type="text" class="form-control invTooltip" id="customerDetailsCustomerID" name="customerDetailsCustomerID" title="This will be auto-generated when you add a new customer" autocomplete="off">
											<div id="customerDetailsCustomerIDSuggestionsDiv" class="customListDivWidth"></div>
										</div>
										<div class="form-group col-lg-2 col-md-6">
											<label for="customerDetailsCustomerMobile">Phone (mobile)<span class="requiredIcon">*</span></label>
											<input type="text" class="form-control invTooltip" id="customerDetailsCustomerMobile" name="customerDetailsCustomerMobile" title="Do not enter leading 0">
										</div>
										<div class="form-group col-lg-3 col-md-6">
											<label for="customerDetailsCustomerEmail">Email</label>
											<input type="email" class="form-control" id="customerDetailsCustomerEmail" name="customerDetailsCustomerEmail">
										</div>
										<div class="form-group col-lg-4 col-md-8">
											<label for="customerDetailsCustomerAddress">Address<span class="requiredIcon">*</span></label>
											<input type="text" class="form-control" id="customerDetailsCustomerAddress" name="customerDetailsCustomerAddress">
										</div>
										<div class="form-group col-lg-3 col-md-4">
											<label for="customerDetailsCustomerDistrict">District</label>
											<select id="customerDetailsCustomerDistrict" name="customerDetailsCustomerDistrict" class="form-control chosenSelect">
												<?php include('inc/districtList.html'); ?>
											</select>
										</div>
									</div>
									<button type="button" id="addCustomer" name="addCustomer" class="btn btn-success">Add Customer</button>
									<button type="button" id="updateCustomerDetailsButton" class="btn btn-primary">Update</button>
									<button type="button" id="deleteCustomerButton" class="btn btn-danger">Delete</button>
									<button type="reset" class="btn">Clear</button>
								</form>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="v-pills-search" role="tabpanel" aria-labelledby="v-pills-search-tab">
						<div class="card card-outline-secondary my-4">
							<div class="card-header">Search Inventory<button id="searchTablesRefresh" name="searchTablesRefresh" class="btn btn-warning float-right btn-sm">Refresh</button></div>
							<div class="card-body">
								<ul class="nav nav-tabs" role="tablist">
									<li class="nav-item">
										<a class="nav-link active" data-toggle="tab" href="#itemSearchTab">Item</a>
									</li>
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#customerSearchTab">Customer</a>
									</li>
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#saleSearchTab">Sale</a>
									</li>
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#purchaseSearchTab">Purchase</a>
									</li>
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#vendorSearchTab">Vendor</a>
									</li>
								</ul>

								<!-- Tab panes -->
								<div class="tab-content">
									<div id="itemSearchTab" class="container-fluid tab-pane active">
										<br>
										<p>Use the grid below to search all details of items</p>
										<!-- <a href="#" class="itemDetailsHover" data-toggle="popover" id="10">wwwee</a> -->
										<div class="table-responsive" id="itemDetailsTableDiv"></div>
									</div>
									<div id="customerSearchTab" class="container-fluid tab-pane fade">
										<br>
										<p>Use the grid below to search all details of customers</p>
										<div class="table-responsive" id="customerDetailsTableDiv"></div>
									</div>
									<div id="saleSearchTab" class="container-fluid tab-pane fade">
										<br>
										<p>Use the grid below to search sale details</p>
										<div class="table-responsive" id="saleDetailsTableDiv"></div>
									</div>
									<div id="purchaseSearchTab" class="container-fluid tab-pane fade">
										<br>
										<p>Use the grid below to search purchase details</p>
										<div class="table-responsive" id="purchaseDetailsTableDiv"></div>
									</div>
									<div id="vendorSearchTab" class="container-fluid tab-pane fade">
										<br>
										<p>Use the grid below to search vendor details</p>
										<div class="table-responsive" id="vendorDetailsTableDiv"></div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="tab-pane fade" id="v-pills-reports" role="tabpanel" aria-labelledby="v-pills-reports-tab">
						<div class="card card-outline-secondary my-4">
							<div class="card-header">Reports<button id="reportsTablesRefresh" name="reportsTablesRefresh" class="btn btn-warning float-right btn-sm">Refresh</button></div>
							<div class="card-body">
								<ul class="nav nav-tabs" role="tablist">
									<li class="nav-item">
										<a class="nav-link active" data-toggle="tab" href="#itemReportsTab">Item</a>
									</li>
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#customerReportsTab">Customer</a>
									</li>
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#saleReportsTab">Sale</a>
									</li>
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#purchaseReportsTab">Purchase</a>
									</li>
									<li class="nav-item">
										<a class="nav-link" data-toggle="tab" href="#vendorReportsTab">Vendor</a>
									</li>
								</ul>

								<!-- Tab panes for reports sections -->
								<div class="tab-content">
									<div id="itemReportsTab" class="container-fluid tab-pane active">
										<br>
										<p>Use the grid below to get reports for items</p>
										<div class="table-responsive" id="itemReportsTableDiv"></div>
									</div>
									<div id="customerReportsTab" class="container-fluid tab-pane fade">
										<br>
										<p>Use the grid below to get reports for customers</p>
										<div class="table-responsive" id="customerReportsTableDiv"></div>
									</div>
									<div id="saleReportsTab" class="container-fluid tab-pane fade">
										<br>
										<!-- <p>Use the grid below to get reports for sales</p> -->
										<form>
											<div class="form-row">
												<div class="form-group col-md-3">
													<label for="saleReportStartDate">Start Date</label>
													<input type="text" class="form-control datepicker" id="saleReportStartDate" value="<?php echo date('Y-m-d'); ?>" name="saleReportStartDate" readonly>
												</div>
												<div class="form-group col-md-3">
													<label for="saleReportEndDate">End Date</label>
													<input type="text" class="form-control datepicker" id="saleReportEndDate" value="<?php echo date('Y-m-d'); ?>" name="saleReportEndDate" readonly>
												</div>
											</div>
											<button type="button" id="showSaleReport" class="btn btn-dark">Show Report</button>
											<button type="reset" id="saleFilterClear" class="btn">Clear</button>
										</form>
										<br><br>
										<div class="table-responsive" id="saleReportsTableDiv"></div>
									</div>
									<div id="purchaseReportsTab" class="container-fluid tab-pane fade">
										<br>
										<!-- <p>Use the grid below to get reports for purchases</p> -->
										<form>
											<div class="form-row">
												<div class="form-group col-md-3">
													<label for="purchaseReportStartDate">Start Date</label>
													<input type="text" class="form-control datepicker" id="purchaseReportStartDate" value="<?php echo date('Y-m-d'); ?>" name="purchaseReportStartDate" readonly>
												</div>
												<div class="form-group col-md-3">
													<label for="purchaseReportEndDate">End Date</label>
													<input type="text" class="form-control datepicker" id="purchaseReportEndDate" value="<?php echo date('Y-m-d'); ?>" name="purchaseReportEndDate" readonly>
												</div>
											</div>
											<button type="button" id="showPurchaseReport" class="btn btn-dark">Show Report</button>
											<button type="reset" id="purchaseFilterClear" class="btn">Clear</button>
										</form>
										<br><br>
										<div class="table-responsive" id="purchaseReportsTableDiv"></div>
									</div>
									<div id="vendorReportsTab" class="container-fluid tab-pane fade">
										<br>
										<p>Use the grid below to get reports for vendors</p>
										<div class="table-responsive" id="vendorReportsTableDiv"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
	require 'inc/footer.php';
	?>
</body>

</html>