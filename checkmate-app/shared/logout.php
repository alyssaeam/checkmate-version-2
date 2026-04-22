<?php
session_start();
session_destroy();
header("Location: ../shared/login.php");
exit();