<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/auth.php');

ensureUserRoleColumn($conn);
bootstrapFirstSuperAdmin($conn);

$loginUsername = '';
$loginPassword = '';
$hashedPassword = '';

if (isset($_POST['loginUsername'])) {
	$loginUsername = $_POST['loginUsername'];
	$loginPassword = $_POST['loginPassword'];

	if (!empty($loginUsername) && !empty($loginPassword)) {

		// Sanitize username
		$loginUsername = filter_var($loginUsername, FILTER_SANITIZE_STRING);

		// Check if username is empty
		if ($loginUsername == '') {
			echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter Username</div>';
			exit();
		}

		// Check if password is empty
		if ($loginPassword == '') {
			echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter Password</div>';
			exit();
		}

		// Encrypt the password
		$hashedPassword = md5($loginPassword);

		// Check the given credentials
		$checkUserSql = 'SELECT userID, fullName, username, status, role FROM user WHERE username = :username AND password = :password';
		$checkUserStatement = $conn->prepare($checkUserSql);
		$checkUserStatement->execute(['username' => $loginUsername, 'password' => $hashedPassword]);

		// Check if user exists or not
		if ($checkUserStatement->rowCount() > 0) {
			// Valid credentials. Hence, start the session
			$row = $checkUserStatement->fetch(PDO::FETCH_ASSOC);

			if (!isset($row['status']) || strtolower((string) $row['status']) !== 'active') {
				echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Your account is not active.</div>';
				exit();
			}

			$_SESSION['loggedIn'] = '1';
			$_SESSION['userID'] = $row['userID'];
			$_SESSION['username'] = $row['username'];
			$_SESSION['fullName'] = $row['fullName'];
			$_SESSION['role'] = isset($row['role']) ? $row['role'] : 'admin';

			echo '<div class="alert alert-success"><button type="button" class="close" data-dismiss="alert">&times;</button>Login success! Redirecting you to home page...</div>';
			exit();
		} else {
			echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Incorrect Username / Password</div>';
			exit();
		}
	} else {
		echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter Username and Password</div>';
		exit();
	}
}
