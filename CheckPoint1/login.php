<?php
session_start();
$conn = mysqli_connect("localhost", "root", "", "medicalportal");

// Check if the connection was successful
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    // Use prepared statements to prevent SQL injection
    $query = "SELECT * FROM user WHERE email = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt === false) {
        die("Prepare failed: " . mysqli_error($conn));
    }

    // Bind parameters
    mysqli_stmt_bind_param($stmt, "s", $email);

    // Execute the query
    if (!mysqli_stmt_execute($stmt)) {
        die("Execute failed: " . mysqli_stmt_error($stmt));
    }

    // Get the result
    $result = mysqli_stmt_get_result($stmt);
    if ($result === false) {
        die("Get result failed: " . mysqli_error($conn));
    }

    // Check if a user was found
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        // Verify the hashed password
        if (password_verify($password, $user["password"])) {
            // Check user status
            if ($user["status"] === 'approved') {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["role"] = $user["role"];
                if ($user["role"] === 'admin') {
                    header("Location: admin.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else if ($user["status"] === 'rejected') {
                $error = "Your profile has been rejected.";
            } else {
                $error = "Your account is pending approval by the admin.";
            }
        } else {
            $error = "Invalid Email or Password!";
        }
    } else {
        $error = "Invalid Email or Password!";
    }

    // Close the statement
    mysqli_stmt_close($stmt);
}

// Close the connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Medical Portal</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f0f4f8; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); width: 350px; }
        h2 { text-align: center; color: #007bff; }
        label { display: block; margin: 10px 0 5px; color: #333; }
        input { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; }
        button { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .error { color: red; text-align: center; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <form method="POST" action="">
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <button type="submit">Login</button>
            <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
            <p style="text-align: center;"><a href="signup.php">Don't have an account? Signup</a></p>
        </form>
    </div>
</body>
</html>