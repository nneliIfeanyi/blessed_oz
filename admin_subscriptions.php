<?php
session_start();
require_once('inc/config/constants.php');
require_once('inc/config/db.php');
require_once('inc/auth.php');
require_once('inc/store.php');

// Redirect if not admin
if (!isset($_SESSION['loggedIn']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

ensureUserRoleColumn($conn);
ensureSubscriptionColumns($conn);
bootstrapFirstSuperAdmin($conn);

$message = '';
$messageType = 'info';

// Handle subscription updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
    
    if ($action === 'updateSubscription') {
        $userID = isset($_POST['userID']) ? (int) $_POST['userID'] : 0;
        $plan = isset($_POST['subscriptionPlan']) ? trim((string) $_POST['subscriptionPlan']) : 'free';
        $cycle = isset($_POST['subscriptionCycle']) ? trim((string) $_POST['subscriptionCycle']) : null;
        $expiresAt = isset($_POST['subscriptionExpiresAt']) ? trim((string) $_POST['subscriptionExpiresAt']) : null;
        
        if ($userID > 0 && in_array($plan, ['free', 'pro', 'business'])) {
            try {
                $updateStmt = $conn->prepare('UPDATE `user` SET subscription_plan = :plan, subscription_cycle = :cycle, subscription_expires_at = :expiresAt WHERE userID = :userID');
                $updateStmt->execute([
                    'userID' => $userID,
                    'plan' => $plan,
                    'cycle' => $cycle,
                    'expiresAt' => $expiresAt
                ]);
                $message = 'Subscription updated successfully.';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating subscription: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Invalid user or plan.';
            $messageType = 'danger';
        }
    }
}

// Get all users with subscription info
$usersStmt = $conn->prepare('SELECT userID, userName, role, subscription_plan, subscription_cycle, subscription_expires_at FROM `user` ORDER BY userName ASC');
$usersStmt->execute();
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Subscriptions - Admin Panel</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/shop-styles.css">
    <style>
        .badge-pro { background-color: #667eea; }
        .badge-free { background-color: #6c757d; }
        .badge-business { background-color: #28a745; }
        .subscription-card {
            border-left: 4px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .subscription-card.pro { border-left-color: #667eea; }
        .subscription-card.business { border-left-color: #28a745; }
        .form-inline-edit {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .expire-soon {
            color: #dc3545;
            font-weight: 600;
        }
        .expire-active {
            color: #28a745;
            font-weight: 600;
        }
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php require_once('inc/navigation.php'); ?>

    <div class="container my-5">
        <div class="admin-header">
            <h1>User Subscription Management</h1>
            <p class="mb-0">Manage user plans, billing cycles, and expiry dates for testing</p>
        </div>

        <?php if ($message) { ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlentities($message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Users</h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($users)) { ?>
                            <p class="text-muted">No users found.</p>
                        <?php } else { ?>
                            <?php foreach ($users as $user) { ?>
                                <div class="subscription-card <?php echo strtolower($user['subscription_plan']); ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="mb-1"><?php echo htmlentities($user['userName'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                            <small class="text-muted"><?php echo ucfirst($user['role']); ?></small>
                                            <br>
                                            <small>
                                                <span class="badge badge-<?php echo strtolower($user['subscription_plan']); ?>">
                                                    <?php echo ucfirst($user['subscription_plan']); ?>
                                                </span>
                                                <?php if ($user['subscription_plan'] !== 'free' && $user['subscription_cycle']) { ?>
                                                    <span class="badge badge-info"><?php echo htmlentities($user['subscription_cycle'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php } ?>
                                            </small>
                                            <?php if ($user['subscription_expires_at']) { ?>
                                                <br>
                                                <small>
                                                    <?php
                                                        $expiryDate = new DateTime($user['subscription_expires_at']);
                                                        $now = new DateTime();
                                                        $interval = $now->diff($expiryDate);
                                                        $daysLeft = $expiryDate > $now ? $interval->days : 0;
                                                    ?>
                                                    <span class="<?php echo $daysLeft <= 7 ? 'expire-soon' : 'expire-active'; ?>">
                                                        Expires: <?php echo $expiryDate->format('M d, Y'); ?>
                                                        <?php if ($daysLeft > 0) { ?>
                                                            (<?php echo $daysLeft; ?> days left)
                                                        <?php } else { ?>
                                                            (Expired)
                                                        <?php } ?>
                                                    </span>
                                                </small>
                                            <?php } ?>
                                        </div>
                                        <div class="col-md-6">
                                            <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editModal" 
                                                onclick="editUser(<?php echo $user['userID']; ?>, '<?php echo htmlentities($user['userName'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo $user['subscription_plan']; ?>', '<?php echo $user['subscription_cycle'] ?? ''; ?>', '<?php echo $user['subscription_expires_at'] ?? ''; ?>')">
                                                Edit Subscription
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <h6>Test Subscription Levels</h6>
                        <p class="small text-muted">Use these to quickly test different subscription tiers.</p>
                        
                        <div class="list-group list-group-flush">
                            <a href="#" class="list-group-item list-group-item-action" data-toggle="modal" data-target="#editModal" onclick="createQuickUpgrade('free')">
                                <strong>Test Free Plan</strong>
                                <small class="d-block text-muted">No offline sync</small>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action" data-toggle="modal" data-target="#editModal" onclick="createQuickUpgrade('pro')">
                                <strong>Test Pro Plan</strong>
                                <small class="d-block text-muted">Active for 30 days</small>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action" data-toggle="modal" data-target="#editModal" onclick="createQuickUpgrade('business')">
                                <strong>Test Business Plan</strong>
                                <small class="d-block text-muted">Active for 90 days</small>
                            </a>
                        </div>

                        <hr>

                        <h6>Plan Features</h6>
                        <div class="small">
                            <p><strong>Free:</strong></p>
                            <ul>
                                <li>Online mode only</li>
                                <li>Basic reports</li>
                            </ul>

                            <p><strong>Pro:</strong></p>
                            <ul>
                                <li>All Free features</li>
                                <li>Offline sync ✨</li>
                                <li>Auto transactions queue</li>
                            </ul>

                            <p><strong>Business:</strong></p>
                            <ul>
                                <li>All Pro features</li>
                                <li>Multiple stores</li>
                                <li>API access</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4><?php echo count(array_filter($users, fn($u) => $u['subscription_plan'] === 'free')); ?></h4>
                                <small class="text-muted">Free Users</small>
                            </div>
                            <div class="col-6">
                                <h4><?php echo count(array_filter($users, fn($u) => $u['subscription_plan'] === 'pro')); ?></h4>
                                <small class="text-muted">Pro Users</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Subscription Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit User Subscription</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="updateSubscription">
                        <input type="hidden" name="userID" id="modalUserID" value="">

                        <div class="form-group">
                            <label>User: <strong id="modalUserName"></strong></label>
                        </div>

                        <div class="form-group">
                            <label for="modalPlan">Subscription Plan</label>
                            <select class="form-control" id="modalPlan" name="subscriptionPlan" onchange="updateCycle()">
                                <option value="free">Free</option>
                                <option value="pro">Pro</option>
                                <option value="business">Business</option>
                            </select>
                        </div>

                        <div class="form-group" id="cycleGroup" style="display: none;">
                            <label for="modalCycle">Billing Cycle</label>
                            <select class="form-control" id="modalCycle" name="subscriptionCycle">
                                <option value="monthly">Monthly</option>
                                <option value="6months">6 Months</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>

                        <div class="form-group" id="expiryGroup" style="display: none;">
                            <label for="modalExpiry">Expiry Date</label>
                            <input type="datetime-local" class="form-control" id="modalExpiry" name="subscriptionExpiresAt" onchange="autoCalculateExpiry()">
                            <small class="form-text text-muted">
                                Leave empty for Free plan. For Pro/Business, set an expiry date.
                            </small>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="setExpiry(30)">+30 days</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="setExpiry(180)">+6 months</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="setExpiry(365)">+1 year</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(userID, userName, plan, cycle, expiresAt) {
            document.getElementById('modalUserID').value = userID;
            document.getElementById('modalUserName').textContent = userName;
            document.getElementById('modalPlan').value = plan;
            document.getElementById('modalCycle').value = cycle || 'monthly';
            document.getElementById('modalExpiry').value = expiresAt ? expiresAt.replace(' ', 'T') : '';
            updateCycle();
        }

        function createQuickUpgrade(plan) {
            // Get first user for testing (or current user)
            const firstUserID = <?php echo count($users) > 0 ? $users[0]['userID'] : '0'; ?>;
            const firstUserName = <?php echo count($users) > 0 ? "'" . htmlentities($users[0]['userName'], ENT_QUOTES, 'UTF-8') . "'" : "'Test User'"; ?>;
            
            if (firstUserID > 0) {
                editUser(firstUserID, firstUserName, plan, 'monthly', '');
                if (plan !== 'free') {
                    setExpiry(30);
                }
            }
        }

        function updateCycle() {
            const plan = document.getElementById('modalPlan').value;
            const cycleGroup = document.getElementById('cycleGroup');
            const expiryGroup = document.getElementById('expiryGroup');

            if (plan === 'free') {
                cycleGroup.style.display = 'none';
                expiryGroup.style.display = 'none';
                document.getElementById('modalExpiry').value = '';
            } else {
                cycleGroup.style.display = 'block';
                expiryGroup.style.display = 'block';
            }
        }

        function setExpiry(days) {
            const now = new Date();
            now.setDate(now.getDate() + days);
            const isoString = now.toISOString().slice(0, 16);
            document.getElementById('modalExpiry').value = isoString;
        }

        function autoCalculateExpiry() {
            // User manually set expiry, keep it
        }
    </script>

    <?php require_once('inc/footer.php'); ?>
</body>
</html>
