<?php
$settingsFile = __DIR__ . '/config/site_settings.json';
$navigationSettings = [
  'siteName' => 'Inventory System',
  'lowStockThreshold' => 5
];
if (file_exists($settingsFile)) {
  $navigationJson = file_get_contents($settingsFile);
  if ($navigationJson !== false) {
    $navigationDecoded = json_decode($navigationJson, true);
    if (is_array($navigationDecoded)) {
      $navigationSettings = array_merge($navigationSettings, $navigationDecoded);
    }
  }
}

$lowStockCount = 0;
if (isset($conn)) {
  try {
    $lowStockThreshold = max(0, (int) $navigationSettings['lowStockThreshold']);
    $lowStockCountStatement = $conn->prepare('SELECT COUNT(*) FROM item WHERE stock <= :threshold');
    $lowStockCountStatement->execute(['threshold' => $lowStockThreshold]);
    $lowStockCount = (int) $lowStockCountStatement->fetchColumn();
  } catch (PDOException $e) {
    $lowStockCount = 0;
  }
}
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
          <span class="nav-link">Welcome <?php echo $_SESSION['fullName']; ?><?php if (isset($_SESSION['role'])) {
                                                                                echo ' (' . htmlspecialchars($_SESSION['role']) . ')';
                                                                              } ?></span>
        </li>
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
          <span class="nav-link"> | </span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="model/login/logout.php">Log Out</a>
        </li>
      </ul>
    </div>
  </div>
</nav>