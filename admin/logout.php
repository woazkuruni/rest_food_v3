<?php
require_once __DIR__ . '/../config/constants.php';
logout_auth();
flash('success', 'Admin logged out.');
redirect('admin/login.php');
