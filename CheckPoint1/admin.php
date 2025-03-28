<?php
session_start();
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "medicalportal");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Fetch admin data
$user_id = $_SESSION["user_id"];
$query = "SELECT * FROM user WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Handle user approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["approve_user"])) {
    $user_id_to_approve = $_POST["user_id"];
    $status = $_POST["status"];

    $query = "UPDATE user SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $status, $user_id_to_approve);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: admin.php#pending-users");
    exit();
}

// Handle user deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_user"])) {
    $user_id_to_delete = $_POST["user_id"];

    $query = "DELETE FROM user WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id_to_delete);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: admin.php#all-users");
    exit();
}

// Handle user edit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_user"])) {
    $user_id_to_edit = $_POST["user_id"];
    $name = $_POST["name"];
    $email = $_POST["email"];
    $role = $_POST["role"];
    $status = $_POST["status"];

    $query = "UPDATE user SET name = ?, email = ?, role = ?, status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $role, $status, $user_id_to_edit);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Update role-specific data
    if ($role == 'patient') {
        $medical_history = $_POST["medical_history"] ?? '';
        $blood_group = $_POST["blood_group"] ?? '';
        $allergies = $_POST["allergies"] ?? '';
        $emergency_contact = $_POST["emergency_contact"] ?? '';

        $query = "INSERT INTO patients (user_id, medical_history, blood_group, allergies, emergency_contact) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE medical_history = ?, blood_group = ?, allergies = ?, emergency_contact = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "issssssss", $user_id_to_edit, $medical_history, $blood_group, $allergies, $emergency_contact, $medical_history, $blood_group, $allergies, $emergency_contact);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else if ($role == 'doctor') {
        $specialization = $_POST["specialization"] ?? '';
        $medical_license_number = $_POST["medical_license_number"] ?? '';
        $hospital_clinic_name = $_POST["hospital_clinic_name"] ?? '';
        $years_of_experience = $_POST["years_of_experience"] ?? '';

        $query = "INSERT INTO doctor (user_id, specialization, medical_license_number, hospital_clinic_name, years_of_experience) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE specialization = ?, medical_license_number = ?, hospital_clinic_name = ?, years_of_experience = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "isssisssi", $user_id_to_edit, $specialization, $medical_license_number, $hospital_clinic_name, $years_of_experience, $specialization, $medical_license_number, $hospital_clinic_name, $years_of_experience);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header("Location: admin.php#all-users");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Medical Portal</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; background-color: #f0f4f8; color: #333; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: linear-gradient(135deg, #ff4d4d, #ff8c1a); padding: 30px 20px; color: white; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); position: fixed; height: 100%; }
        .sidebar h2 { font-size: 24px; margin-bottom: 30px; text-align: center; }
        .sidebar a { display: block; padding: 12px 15px; text-decoration: none; color: white; font-size: 16px; border-radius: 5px; margin-bottom: 10px; transition: background 0.3s; }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.2); }
        .main-content { flex-grow: 1; padding: 40px; margin-left: 250px; }
        .card { background-color: white; border-radius: 10px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); }
        .card h3 { color: #ff4d4d; font-size: 20px; margin-bottom: 15px; }
        .card p { margin: 8px 0; font-size: 15px; }
        .card a { color: #ff4d4d; text-decoration: none; margin-right: 10px; }
        .card a:hover { text-decoration: underline; }
        button, input[type="submit"] { background-color: #ff4d4d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; transition: background 0.3s; }
        button:hover, input[type="submit"]:hover { background-color: #e60000; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f4f4f4; }
        .error { color: red; margin-top: 10px; }
        .success { color: green; margin-top: 10px; }
        .role-specific { display: none; }
        @media (max-width: 768px) { .sidebar { width: 100%; height: auto; position: static; padding: 20px; } .main-content { margin-left: 0; padding: 20px; } }
    </style>
    <script>
        function toggleFields(userId) {
            const role = document.getElementById(`role-${userId}`).value;
            document.getElementById(`patient-fields-${userId}`).style.display = role === "patient" ? "block" : "none";
            document.getElementById(`doctor-fields-${userId}`).style.display = role === "doctor" ? "block" : "none";
        }
    </script>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <h2>Welcome, <?php echo $admin["name"]; ?></h2>
            <a href="#pending-users">Pending Users</a>
            <a href="#all-users">All Users</a>
            <a href="#settings">Settings</a>
            <a href="logout.php">Logout</a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Admin Profile -->
            <div class="card" id="profile">
                <h3>Admin Profile</h3>
                <p><strong>Name:</strong> <?php echo $admin["name"]; ?></p>
                <p><strong>Email:</strong> <?php echo $admin["email"]; ?></p>
                <button>Edit Profile</button>
            </div>

            <!-- Pending Users -->
            <div class="card" id="pending-users">
                <h3>Pending Users</h3>
                <table>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                    <?php
                    $query = "SELECT * FROM user WHERE status = 'pending' AND role != 'admin'";
                    $result = mysqli_query($conn, $query);
                    while ($user = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>{$user['name']}</td>";
                        echo "<td>{$user['email']}</td>";
                        echo "<td>{$user['role']}</td>";
                        echo "<td>";
                        echo "<form method='POST' action='' style='display:inline;'>";
                        echo "<input type='hidden' name='user_id' value='{$user['id']}'>";
                        echo "<input type='hidden' name='status' value='approved'>";
                        echo "<input type='submit' name='approve_user' value='Approve' style='background-color: green;'>";
                        echo "</form> ";
                        echo "<form method='POST' action='' style='display:inline;'>";
                        echo "<input type='hidden' name='user_id' value='{$user['id']}'>";
                        echo "<input type='hidden' name='status' value='rejected'>";
                        echo "<input type='submit' name='approve_user' value='Reject' style='background-color: red;'>";
                        echo "</form>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </table>
            </div>

            <!-- All Users -->
            <div class="card" id="all-users">
                <h3>All Users</h3>
                <table>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    <?php
                    $query = "SELECT * FROM user WHERE role != 'admin'";
                    $result = mysqli_query($conn, $query);
                    while ($user = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>{$user['name']}</td>";
                        echo "<td>{$user['email']}</td>";
                        echo "<td>{$user['role']}</td>";
                        echo "<td>{$user['status']}</td>";
                        echo "<td>";
                        echo "<button onclick=\"document.getElementById('edit-form-{$user['id']}').style.display='block'\">Edit</button> ";
                        echo "<form method='POST' action='' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to delete this user?\");'>";
                        echo "<input type='hidden' name='user_id' value='{$user['id']}'>";
                        echo "<input type='submit' name='delete_user' value='Delete' style='background-color: red;'>";
                        echo "</form>";
                        echo "</td>";
                        echo "</tr>";

                        // Edit form (hidden by default)
                        echo "<tr id='edit-form-{$user['id']}' style='display:none;'>";
                        echo "<td colspan='5'>";
                        echo "<form method='POST' action=''>";
                        echo "<input type='hidden' name='user_id' value='{$user['id']}'>";
                        echo "<label>Name:</label>";
                        echo "<input type='text' name='name' value='{$user['name']}' required>";
                        echo "<label>Email:</label>";
                        echo "<input type='email' name='email' value='{$user['email']}' required>";
                        echo "<label>Role:</label>";
                        echo "<select name='role' id='role-{$user['id']}' onchange='toggleFields({$user['id']})' required>";
                        echo "<option value='patient' " . ($user['role'] == 'patient' ? 'selected' : '') . ">Patient</option>";
                        echo "<option value='doctor' " . ($user['role'] == 'doctor' ? 'selected' : '') . ">Doctor</option>";
                        echo "</select>";
                        echo "<label>Status:</label>";
                        echo "<select name='status' required>";
                        echo "<option value='pending' " . ($user['status'] == 'pending' ? 'selected' : '') . ">Pending</option>";
                        echo "<option value='approved' " . ($user['status'] == 'approved' ? 'selected' : '') . ">Approved</option>";
                        echo "<option value='rejected' " . ($user['status'] == 'rejected' ? 'selected' : '') . ">Rejected</option>";
                        echo "</select>";

                        // Fetch role-specific data
                        if ($user['role'] == 'patient') {
                            $query = "SELECT * FROM patients WHERE user_id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "i", $user['id']);
                            mysqli_stmt_execute($stmt);
                            $result2 = mysqli_stmt_get_result($stmt);
                            $patient_data = mysqli_fetch_assoc($result2);
                            mysqli_stmt_close($stmt);
                        } else if ($user['role'] == 'doctor') {
                            $query = "SELECT * FROM doctor WHERE user_id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, "i", $user['id']);
                            mysqli_stmt_execute($stmt);
                            $result2 = mysqli_stmt_get_result($stmt);
                            $doctor_data = mysqli_fetch_assoc($result2);
                            mysqli_stmt_close($stmt);
                        }

                        // Patient-specific fields
                        echo "<div id='patient-fields-{$user['id']}' class='role-specific' style='display:" . ($user['role'] == 'patient' ? 'block' : 'none') . ";'>";
                        echo "<label>Medical History:</label>";
                        echo "<input type='text' name='medical_history' value='" . ($patient_data['medical_history'] ?? '') . "'>";
                        echo "<label>Blood Group:</label>";
                        echo "<input type='text' name='blood_group' value='" . ($patient_data['blood_group'] ?? '') . "'>";
                        echo "<label>Allergies:</label>";
                        echo "<input type='text' name='allergies' value='" . ($patient_data['allergies'] ?? '') . "'>";
                        echo "<label>Emergency Contact:</label>";
                        echo "<input type='text' name='emergency_contact' value='" . ($patient_data['emergency_contact'] ?? '') . "'>";
                        echo "</div>";

                        // Doctor-specific fields
                        echo "<div id='doctor-fields-{$user['id']}' class='role-specific' style='display:" . ($user['role'] == 'doctor' ? 'block' : 'none') . ";'>";
                        echo "<label>Specialization:</label>";
                        echo "<input type='text' name='specialization' value='" . ($doctor_data['specialization'] ?? '') . "'>";
                        echo "<label>Medical License Number:</label>";
                        echo "<input type='text' name='medical_license_number' value='" . ($doctor_data['medical_license_number'] ?? '') . "'>";
                        echo "<label>Hospital/Clinic Name:</label>";
                        echo "<input type='text' name='hospital_clinic_name' value='" . ($doctor_data['hospital_clinic_name'] ?? '') . "'>";
                        echo "<label>Years of Experience:</label>";
                        echo "<input type='number' name='years_of_experience' value='" . ($doctor_data['years_of_experience'] ?? '') . "'>";
                        echo "</div>";

                        echo "<input type='submit' name='edit_user' value='Save Changes'>";
                        echo "</form>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </table>
            </div>
        </div>
    </div>
    <?php mysqli_close($conn); ?>
</body>
</html>