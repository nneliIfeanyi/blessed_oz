<?php
session_start();

unset($_SESSION['loggedIn']);
unset($_SESSION['userID']);
unset($_SESSION['username']);
unset($_SESSION['fullName']);
unset($_SESSION['role']);
unset($_SESSION['activeStoreID']);
unset($_SESSION['activeStoreName']);
session_destroy();
header('Location: ../../login.php');
exit();
