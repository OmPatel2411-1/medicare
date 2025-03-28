<?php
$conn = mysqli_connect("localhost", "root", "", "medicalportal");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT); // Hash the password
    $role = $_POST["role"];
    $status = 'pending'; // Set status to pending by default

    // Insert into user table
    $query = "INSERT INTO user (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $password, $role, $status);
    if (mysqli_stmt_execute($stmt)) {
        $user_id = mysqli_insert_id($conn);

        // Insert role-specific data
        if ($role == 'patient') {
            $medical_history = $_POST["medical_history"] ?? '';
            $blood_group = $_POST["blood_group"] ?? '';
            $allergies = $_POST["allergies"] ?? '';
            $emergency_contact = $_POST["emergency_contact"] ?? '';
            $query = "INSERT INTO patients (user_id, medical_history, blood_group, allergies, emergency_contact) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "issss", $user_id, $medical_history, $blood_group, $allergies, $emergency_contact);
        } else if ($role == 'doctor') {
            $specialization = $_POST["specialization"] ?? '';
            $medical_license_number = $_POST["medical_license_number"] ?? '';
            $hospital_clinic_name = $_POST["hospital_clinic_name"] ?? '';
            $years_of_experience = $_POST["years_of_experience"] ?? '';
            $query = "INSERT INTO doctor (user_id, specialization, medical_license_number, hospital_clinic_name, years_of_experience) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "isssi", $user_id, $specialization, $medical_license_number, $hospital_clinic_name, $years_of_experience);
        }

        if (mysqli_stmt_execute($stmt)) {
            $message = "Registration successful! Please wait for admin approval.";
        } else {
            $error = "Error: " . mysqli_stmt_error($stmt);
        }
    } else {
        $error = "Error: " . mysqli_stmt_error($stmt);
    }

    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - Medical Portal</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f0f4f8; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .signup-container { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); width: 400px; }
        h2 { text-align: center; color: #007bff; }
        label { display: block; margin: 10px 0 5px; color: #333; }
        input, select { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; }
        button { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .error { color: red; text-align: center; margin-top: 10px; }
        .success { color: green; text-align: center; margin-top: 10px; }
        .role-specific { display: none; }
    </style>
    <script>
        function toggleFields() {
            const role = document.getElementById("role").value;
            document.getElementById("patient-fields").style.display = role === "patient" ? "block" : "none";
            document.getElementById("doctor-fields").style.display = role === "doctor" ? "block" : "none";
        }
    </script>
</head>
<body>
    <div class="signup-container">
        <h2>Signup</h2>
        <form method="POST" action="">
            <label>Name</label>
            <input type="text" name="name" required>
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <label>Role</label>
            <select name="role" id="role" onchange="toggleFields()" required>
                <option value="">Select Role</option>
                <option value="patient">Patient</option>
                <option value="doctor">Doctor</option>
            </select>

            <!-- Patient-specific fields -->
            <div id="patient-fields" class="role-specific">
                <label>Medical History</label>
                <input type="text" name="medical_history">
                <label>Blood Group</label>
                <input type="text" name="blood_group">
                <label>Allergies</label>
                <input type="text" name="allergies">
                <label>Emergency Contact</label>
                <input type="text" name="emergency_contact">
            </div>

            <!-- Doctor-specific fields -->
            <div id="doctor-fields" class="role-specific">
                <label>Specialization</label>
                <input type="text" name="specialization">
                <label>Medical License Number</label>
                <input type="text" name="medical_license_number">
                <label>Hospital/Clinic Name</label>
                <input type="text" name="hospital_clinic_name">
                <label>Years of Experience</label>
                <input type="number" name="years_of_experience">
            </div>

            <button type="submit">Signup</button>
            <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
            <?php if (isset($message)) { echo "<p class='success'>$message</p>"; } ?>
        </form>
    </div>
</body>
</html>