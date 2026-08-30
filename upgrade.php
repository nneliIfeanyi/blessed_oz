<?php
session_start();
require_once('inc/config/constants.php');
require_once('inc/config/db.php');
require_once('inc/auth.php');

// Redirect to login if not authenticated
if (!isset($_SESSION['userID'])) {
	header('Location: login.php');
	exit();
}

// Load subscription info
loadUserSubscriptionSession($conn, $_SESSION['userID']);
$isProActive = isProActive();
$currentPlan = $_SESSION['subscription_plan'] ?? 'free';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Upgrade to Pro - Inventory System</title>
	<link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" href="assets/css/shop-styles.css">
	<style>
		.pricing-card {
			border: 2px solid #ddd;
			border-radius: 8px;
			transition: all 0.3s ease;
			min-height: 500px;
		}
		.pricing-card.active {
			border-color: #007bff;
			box-shadow: 0 0 15px rgba(0, 123, 255, 0.3);
		}
		.pricing-card.recommended {
			position: relative;
			top: -10px;
		}
		.badge-recommended {
			position: absolute;
			top: -10px;
			right: 10px;
			background-color: #28a745;
		}
		.pricing-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 40px 20px;
			text-align: center;
			border-bottom: 2px solid #ddd;
		}
		.feature-list {
			list-style: none;
			padding: 0;
		}
		.feature-list li {
			padding: 12px 0;
			border-bottom: 1px solid #eee;
		}
		.feature-list li:last-child {
			border-bottom: none;
		}
		.feature-list li.included::before {
			content: "✓ ";
			color: #28a745;
			font-weight: bold;
			margin-right: 8px;
		}
		.feature-list li.excluded {
			color: #ccc;
		}
		.feature-list li.excluded::before {
			content: "✗ ";
			color: #dc3545;
			font-weight: bold;
			margin-right: 8px;
		}
		.btn-upgrade {
			width: 100%;
			margin-top: 20px;
		}
		.billing-toggle {
			text-align: center;
			margin: 30px 0;
		}
		.toggle-buttons {
			display: inline-flex;
			border: 1px solid #ddd;
			border-radius: 5px;
			overflow: hidden;
		}
		.toggle-btn {
			padding: 10px 20px;
			border: none;
			background: white;
			cursor: pointer;
			font-weight: 600;
		}
		.toggle-btn.active {
			background-color: #007bff;
			color: white;
		}
		.currency-toggle {
			text-align: center;
			margin-bottom: 20px;
		}
		.currency-toggle button {
			margin: 0 5px;
		}
		.price-display {
			font-size: 2.5em;
			font-weight: bold;
			margin: 20px 0;
			color: #333;
		}
		.save-badge {
			display: inline-block;
			background-color: #dc3545;
			color: white;
			padding: 5px 10px;
			border-radius: 3px;
			font-size: 0.9em;
			margin-top: 10px;
		}
	</style>
</head>
<body>
	<?php require_once('inc/navigation.php'); ?>

	<div class="container my-5">
		<div class="pricing-header">
			<h1>Unlock Offline Sync with Pro</h1>
			<p class="lead mb-0">Keep your inventory running anywhere, anytime</p>
		</div>

		<!-- Currency Toggle -->
		<div class="currency-toggle mt-4">
			<button class="btn btn-sm btn-outline-primary currency-btn" data-currency="usd">USD ($)</button>
			<button class="btn btn-sm btn-outline-primary currency-btn active" data-currency="ngn">NGN (₦)</button>
		</div>

		<!-- Billing Cycle Toggle -->
		<div class="billing-toggle">
			<div class="toggle-buttons">
				<button class="toggle-btn active billing-cycle-btn" data-cycle="monthly">Monthly</button>
				<button class="toggle-btn billing-cycle-btn" data-cycle="6months">6 Months (20% OFF)</button>
				<button class="toggle-btn billing-cycle-btn" data-cycle="yearly">Yearly (40% OFF)</button>
			</div>
		</div>

		<div class="row mt-5">
			<!-- Free Plan -->
			<div class="col-md-6 col-lg-4 mb-4">
				<div class="card pricing-card <?php echo !$isProActive ? 'active' : ''; ?>">
					<div class="card-header bg-light">
						<h5 class="card-title mb-0">Free</h5>
						<small class="text-muted">Current plan</small>
					</div>
					<div class="card-body">
						<div class="price-display">$0<span style="font-size: 0.5em">/mo</span></div>
						<ul class="feature-list">
							<li class="included">Online Sales & Purchases</li>
							<li class="included">Basic Reports</li>
							<li class="included">Customer Management</li>
							<li class="included">Stock Tracking</li>
							<li class="excluded">Offline Mode</li>
							<li class="excluded">Automatic Sync</li>
							<li class="excluded">Priority Support</li>
						</ul>
					</div>
					<div class="card-footer bg-light">
						<?php if (!$isProActive) { ?>
							<button class="btn btn-secondary btn-upgrade" disabled>Current Plan</button>
						<?php } else { ?>
							<button class="btn btn-secondary btn-upgrade" disabled>Downgrade</button>
						<?php } ?>
					</div>
				</div>
			</div>

			<!-- Pro Plan -->
			<div class="col-md-6 col-lg-4 mb-4">
				<div class="card pricing-card recommended <?php echo $isProActive ? 'active' : ''; ?>">
					<?php if (!$isProActive) { ?>
						<span class="badge badge-recommended">Recommended</span>
					<?php } ?>
					<div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
						<h5 class="card-title mb-0">Pro</h5>
						<small>Most Popular</small>
					</div>
					<div class="card-body">
						<div class="price-display" id="proPrice">₦15,000<span style="font-size: 0.5em">/mo</span></div>
						<div class="save-badge" id="saveBadge" style="display: none;"></div>
						<ul class="feature-list">
							<li class="included">Everything in Free</li>
							<li class="included">Offline Mode ✨</li>
							<li class="included">Automatic Sync</li>
							<li class="included">Offline Transactions Queue</li>
							<li class="included">Multi-device Sync</li>
							<li class="included">Priority Email Support</li>
							<li class="included">Monthly Backups</li>
						</ul>
					</div>
					<div class="card-footer bg-light">
						<?php if ($isProActive) { ?>
							<button class="btn btn-success btn-upgrade" disabled>Active Subscription</button>
						<?php } else { ?>
							<button class="btn btn-primary btn-upgrade" id="upgradeBtn" data-cycle="monthly" data-currency="ngn">Upgrade Now</button>
						<?php } ?>
					</div>
				</div>
			</div>

			<!-- Business Plan (Future) -->
			<div class="col-md-6 col-lg-4 mb-4">
				<div class="card pricing-card">
					<div class="card-header bg-light">
						<h5 class="card-title mb-0">Business</h5>
						<small class="text-muted">Coming Soon</small>
					</div>
					<div class="card-body">
						<div class="price-display">Custom</div>
						<ul class="feature-list">
							<li class="included">Everything in Pro</li>
							<li class="included">Multiple Stores</li>
							<li class="included">Advanced Analytics</li>
							<li class="included">Custom Integrations</li>
							<li class="included">Dedicated Support</li>
							<li class="included">API Access</li>
							<li class="included">SLA Guarantee</li>
						</ul>
					</div>
					<div class="card-footer bg-light">
						<button class="btn btn-secondary btn-upgrade" disabled>Coming Soon</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Pricing Details Table -->
		<div class="row mt-5">
			<div class="col-12">
				<h3>Detailed Pricing</h3>
				<div class="table-responsive">
					<table class="table table-hover">
						<thead class="table-light">
							<tr>
								<th>Plan</th>
								<th>Monthly</th>
								<th>6 Months</th>
								<th>Yearly</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong>Free</strong></td>
								<td>$0</td>
								<td>$0</td>
								<td>$0</td>
							</tr>
							<tr>
								<td><strong>Pro (USD)</strong></td>
								<td>$10/mo</td>
								<td>$48 (20% OFF)</td>
								<td>$72 (40% OFF)</td>
							</tr>
							<tr>
								<td><strong>Pro (NGN)</strong></td>
								<td>₦15,000/mo</td>
								<td>₦72,000 (20% OFF)</td>
								<td>₦108,000 (40% OFF)</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- FAQ Section -->
		<div class="row mt-5">
			<div class="col-lg-8 offset-lg-2">
				<h3>Frequently Asked Questions</h3>
				<div class="accordion" id="faqAccordion">
					<div class="card">
						<div class="card-header" id="faq1">
							<h2 class="mb-0">
								<button class="btn btn-link" type="button" data-toggle="collapse" data-target="#faq1Body">
									What is offline mode?
								</button>
							</h2>
						</div>
						<div id="faq1Body" class="collapse" data-parent="#faqAccordion">
							<div class="card-body">
								Offline mode allows you to continue entering sales and purchases even when your internet connection is down. All transactions are queued locally and automatically synced to the server when you're back online.
							</div>
						</div>
					</div>
					<div class="card">
						<div class="card-header" id="faq2">
							<h2 class="mb-0">
								<button class="btn btn-link" type="button" data-toggle="collapse" data-target="#faq2Body">
									Can I cancel my subscription?
								</button>
							</h2>
						</div>
						<div id="faq2Body" class="collapse" data-parent="#faqAccordion">
							<div class="card-body">
								Yes, you can cancel anytime. Your Pro features will remain active until the end of your billing cycle.
							</div>
						</div>
					</div>
					<div class="card">
						<div class="card-header" id="faq3">
							<h2 class="mb-0">
								<button class="btn btn-link" type="button" data-toggle="collapse" data-target="#faq3Body">
									Is my data secure?
								</button>
							</h2>
						</div>
						<div id="faq3Body" class="collapse" data-parent="#faqAccordion">
							<div class="card-body">
								Yes. All data is encrypted during sync and stored securely on our servers. Your offline data is never shared or used for any purpose other than syncing your inventory.
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="vendor/jquery/jquery.min.js"></script>
	<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
	<script>
		const prices = {
			usd: {
				monthly: { price: '$10', save: '' },
				'6months': { price: '$48', save: 'Save $12' },
				yearly: { price: '$72', save: 'Save $48' }
			},
			ngn: {
				monthly: { price: '₦15,000', save: '' },
				'6months': { price: '₦72,000', save: 'Save ₦18,000' },
				yearly: { price: '₦108,000', save: 'Save ₦72,000' }
			}
		};

		let currentCurrency = 'ngn';
		let currentCycle = 'monthly';

		$('.currency-btn').on('click', function () {
			currentCurrency = $(this).data('currency');
			$('.currency-btn').removeClass('active');
			$(this).addClass('active');
			updatePricing();
		});

		$('.billing-cycle-btn').on('click', function () {
			currentCycle = $(this).data('cycle');
			$('.billing-cycle-btn').removeClass('active');
			$(this).addClass('active');
			updatePricing();
		});

		function updatePricing() {
			const pricing = prices[currentCurrency][currentCycle];
			$('#proPrice').html(pricing.price + '<span style="font-size: 0.5em">' + (currentCycle === 'monthly' ? '/mo' : '') + '</span>');
			const saveBadge = $('#saveBadge');
			if (pricing.save) {
				saveBadge.text(pricing.save).show();
			} else {
				saveBadge.hide();
			}
			$('#upgradeBtn').data('cycle', currentCycle).data('currency', currentCurrency);
		}

		$('#upgradeBtn').on('click', function () {
			const cycle = $(this).data('cycle');
			const currency = $(this).data('currency');
			alert('Thank you for upgrading! This feature will integrate with a payment provider (Stripe, Paystack, etc.) soon.\n\nFor now, please contact support to upgrade manually.');
			// In a real implementation, redirect to payment gateway
			// window.location.href = 'checkout.php?cycle=' + cycle + '&currency=' + currency;
		});
	</script>

	<?php require_once('inc/footer.php'); ?>
</body>
</html>
