<?php
$stored_hash = "$2y$10$z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5z5"; // Replace with the hash from the database
$password_to_check = "admin123";

if (password_verify($password_to_check, $stored_hash)) {
    echo "Password matches!";
} else {
    echo "Password does NOT match!";
}
?>