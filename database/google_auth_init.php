<?php
// database/google_auth_init.php

include("google_auth.php");

// Redirigir a Google para autorización
$authUrl = getGoogleAuthUrl();
header("Location: " . $authUrl);
exit;
?>