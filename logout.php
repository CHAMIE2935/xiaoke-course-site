<?php
require __DIR__ . '/includes/init.php';
session_destroy();
redirect(url('index.php'));
