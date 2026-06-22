<?php
require_once dirname(__DIR__) . '/includes/functions.php';
session_destroy();
session_start();
flash('success', 'Voce saiu da sua conta.');
redirect('index.php');
