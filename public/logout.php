<?php

require_once '/var/www/src/config/session.php';

unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_name'],
    $_SESSION['employee_id'],
    $_SESSION['employee_role'],
    $_SESSION['employee_name'],
    $_SESSION['last_order_id']
);

session_regenerate_id(true);

header('Location: /');
exit;
