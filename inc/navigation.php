<?php
require_once(__DIR__ . '/store.php');

$settingsFile = __DIR__ . '/config/site_settings.json';
$navigationSettings = getDefaultStoreSettings();

$lowStockCount = 0;
if (isset($conn)) {
  try {
    if (isset($_SESSION['loggedIn']) && $_SESSION['loggedIn'] === '1') {
      ensureActiveStoreSession($conn);
    }

    $activeStoreID = isset($_SESSION['activeStoreID']) ? (int) $_SESSION['activeStoreID'] : 1;
    $navigationSettings = getStoreSettings($conn, $activeStoreID, $settingsFile);

    $lowStockThreshold = max(0, (int) $navigationSettings['lowStockThreshold']);
    $lowStockCountStatement = $conn->prepare('SELECT COUNT(*) FROM item WHERE stock <= :threshold AND storeID = :storeID');
    $lowStockCountStatement->execute(['threshold' => $lowStockThreshold, 'storeID' => $activeStoreID]);
    $lowStockCount = (int) $lowStockCountStatement->fetchColumn();
  } catch (PDOException $e) {
    $lowStockCount = 0;
  }
}

$activeStores = [];
if (isset($conn) && isset($_SESSION['loggedIn']) && $_SESSION['loggedIn'] === '1') {
  $activeStores = getActiveStores($conn);
}

$activeStoreID = isset($_SESSION['activeStoreID']) ? (int) $_SESSION['activeStoreID'] : 1;
$activeStoreName = isset($_SESSION['activeStoreName']) ? $_SESSION['activeStoreName'] : 'Main Store';
$returnUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/inventory-system/index.php';
?>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <div class="container">
    <a class="navbar-brand" href="<?php echo ROOT_URL; ?>"><?php echo htmlspecialchars($navigationSettings['siteName']); ?></a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarResponsive">
      <ul class="navbar-nav ml-auto">
        <!-- <li class="nav-item">
				<form class="form-inline" action="/action_page.php">
					<input class="form-control col-md-8 mr-sm-2" type="text" placeholder="Search">
					<button class="btn btn-success" type="submit">Search</button>
				</form>
			</li> -->
        <li class="nav-item">
          <span class="nav-link">Welcome <?php if (isset($_SESSION['role'])) {
                                            echo ' (' . htmlspecialchars($_SESSION['role']) . ')';
                                          } ?></span>
        </li>
        <?php if (count($activeStores) > 0) { ?>
          <li class="nav-item d-flex align-items-center mr-2">
            <form class="form-inline" action="model/store/switchStore.php" method="post">
              <input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars($returnUrl); ?>">
              <label for="storeSwitcher" class="text-light mr-2 mb-0" style="font-size:0.85rem;">Store</label>
              <select id="storeSwitcher" name="storeID" class="form-control form-control-sm" onchange="this.form.submit()" title="Switch Store">
                <?php foreach ($activeStores as $store) {
                  $storeID = (int) $store['storeID'];
                  $selected = $storeID === $activeStoreID ? 'selected' : '';
                  echo '<option value="' . $storeID . '" ' . $selected . '>' . htmlspecialchars($store['storeName']) . '</option>';
                } ?>
              </select>
            </form>
          </li>
        <?php } else { ?>
          <li class="nav-item">
            <span class="nav-link">Store: <?php echo htmlspecialchars($activeStoreName); ?></span>
          </li>
        <?php } ?>
        <li class="nav-item">
          <a class="nav-link" href="lowStockAlerts.php">Low Stock <?php
                                                                  $badgeClass = $lowStockCount > 0 ? 'badge-danger' : 'badge-secondary';
                                                                  echo '<sup class="badge ' . $badgeClass . '">' . (int) $lowStockCount . '</sup>';
                                                                  ?></a>
        </li>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') { ?>
          <li class="nav-item">
            <a class="nav-link" href="settings.php">Settings</a>
          </li>
        <?php } ?>
        <li class="nav-item">
          <a class="nav-link" href="model/login/logout.php">Log Out</a>
        </li>
      </ul>
    </div>
  </div>
</nav>