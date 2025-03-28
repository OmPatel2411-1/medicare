<?php
session_start();
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || $_SESSION["role"] === 'admin') {
    header("Location: login.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "medicalportal");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

// Check user status
$query = "SELECT status FROM user WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_status = mysqli_fetch_assoc($result)["status"];
mysqli_stmt_close($stmt);

if ($user_status !== 'approved') {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Fetch user data
$query = "SELECT * FROM user WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Fetch role-specific data
if ($role == 'patient') {
    $query = "SELECT * FROM patients WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $patient_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Generate graphs using Python
    $patient_id = $patient_data["id"];
    $python_script = "D:\\Xampp\\htdocs\\fulltemp\\new\\generate_graphs.py"; // Updated path
    $python_cmd = "python \"$python_script\" $patient_id 2>&1";
    $output = [];
    $return_var = 0;
    exec($python_cmd, $output, $return_var);
    if ($return_var !== 0) {
        $graph_error = "Error generating graphs: " . implode("\n", $output);
    }
} else {
    $query = "SELECT * FROM doctor WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $doctor_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Handle Doctor Availability Submission
if ($role == 'doctor' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_slot"])) {
    $date = $_POST["date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $doctor_id = $doctor_data["id"];

    try {
        // Check if the time slot already exists for the doctor on the given date
        $query = "SELECT * FROM doctor_availability WHERE doctor_id = ? AND date = ? AND start_time = ? AND end_time = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "isss", $doctor_id, $date, $start_time, $end_time);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 0) {
            // Insert the new time slot if it doesn't exist
            $query = "INSERT INTO doctor_availability (doctor_id, date, start_time, end_time) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "isss", $doctor_id, $date, $start_time, $end_time);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $availability_error = "This time slot is already added!";
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) { // Duplicate entry error code
            $availability_error = "This time slot is already added!";
        } else {
            $availability_error = "Error adding time slot: " . $e->getMessage();
        }
    }

    // Redirect to the same page to prevent form resubmission on refresh
    header("Location: dashboard.php#availability");
    exit();
}

// Handle Appointment Booking
if ($role == 'patient' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["book_appointment"])) {
    $doctor_id = $_POST["doctor_id"];
    $date = $_POST["date"];
    $time = $_POST["time"];
    $patient_id = $patient_data["id"];
    
    // Check if time is within doctor's availability
    $query = "SELECT * FROM doctor_availability WHERE doctor_id = ? AND date = ? AND ? BETWEEN start_time AND end_time";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iss", $doctor_id, $date, $time);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $query = "INSERT INTO appointments (patient_id, doctor_id, date, time) VALUES (?, ?, ?, ?)";
        $stmt2 = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt2, "iiss", $patient_id, $doctor_id, $date, $time);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
        $booking_message = "Appointment booked successfully!";
    } else {
        $booking_error = "Selected time is not available!";
    }
    mysqli_stmt_close($stmt);

    // Redirect to prevent form resubmission on refresh
    header("Location: dashboard.php#book-appointment");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $role == 'patient' ? 'Patient' : 'Doctor'; ?> Dashboard - Medical Portal</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; background-color: #f0f4f8; color: #333; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: linear-gradient(135deg, #007bff, #00c4cc); padding: 30px 20px; color: white; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); position: fixed; height: 100%; }
        .sidebar h2 { font-size: 24px; margin-bottom: 30px; text-align: center; }
        .sidebar a { display: block; padding: 12px 15px; text-decoration: none; color: white; font-size: 16px; border-radius: 5px; margin-bottom: 10px; transition: background 0.3s; }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.2); }
        .main-content { flex-grow: 1; padding: 40px; margin-left: 250px; }
        .card { background-color: white; border-radius: 10px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); }
        .card h3 { color: #007bff; font-size: 20px; margin-bottom: 15px; }
        .card p { margin: 8px 0; font-size: 15px; }
        .card a { color: #007bff; text-decoration: none; margin-right: 10px; }
        .card a:hover { text-decoration: underline; }
        button, input[type="submit"] { background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; transition: background 0.3s; }
        button:hover, input[type="submit"]:hover { background-color: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f4f4f4; }
        .error { color: red; margin-top: 10px; }
        .success { color: green; margin-top: 10px; }
        img.graph { max-width: 100%; height: auto; }
        @media (max-width: 768px) { .sidebar { width: 100%; height: auto; position: static; padding: 20px; } .main-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <h2>Welcome, <?php echo $user["name"]; ?></h2>
            <?php if ($role == 'patient') { ?>
                <a href="#profile">Profile</a>
                <a href="#appointments">Appointments</a>
                <a href="#medical-records">Medical Records</a>
                <a href="#medications">Medications</a>
                <a href="#vitals">Health Vitals</a>
                <a href="#book-appointment">Book Appointment</a>
            <?php } else { ?>
                <a href="#profile">Profile</a>
                <a href="#patients">My Patients</a>
                <a href="#appointments">Appointments</a>
                <a href="#schedule">Schedule</a>
                <a href="#availability">Set Availability</a>
            <?php } ?>
            <a href="#settings">Settings</a>
            <a href="logout.php">Logout</a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Profile Section -->
            <div class="card" id="profile">
                <h3><?php echo $role == 'patient' ? 'Patient' : 'Doctor'; ?> Profile</h3>
                <p><strong>Name:</strong> <?php echo $user["name"]; ?></p>
                <p><strong>Email:</strong> <?php echo $user["email"]; ?></p>
                <?php if ($role == 'patient') { ?>
                    <p><strong>Medical History:</strong> <?php echo $patient_data["medical_history"] ?: 'Not provided'; ?></p>
                    <p><strong>Blood Group:</strong> <?php echo $patient_data["blood_group"] ?: 'Not provided'; ?></p>
                    <p><strong>Allergies:</strong> <?php echo $patient_data["allergies"] ?: 'None'; ?></p>
                    <p><strong>Emergency Contact:</strong> <?php echo $patient_data["emergency_contact"] ?: 'Not provided'; ?></p>
                <?php } else { ?>
                    <p><strong>Specialization:</strong> <?php echo $doctor_data["specialization"]; ?></p>
                    <p><strong>Medical License Number:</strong> <?php echo $doctor_data["medical_license_number"]; ?></p>
                    <p><strong>Hospital/Clinic:</strong> <?php echo $doctor_data["hospital_clinic_name"]; ?></p>
                    <p><strong>Years of Experience:</strong> <?php echo $doctor_data["years_of_experience"]; ?></p>
                <?php } ?>
                <button>Edit Profile</button>
            </div>

            <?php if ($role == 'patient') { ?>
                <!-- Patient-Specific Sections -->
                <div class="card" id="appointments">
                    <h3>Upcoming Appointments</h3>
                    <?php
                    $patient_id = $patient_data["id"];
                    $query = "SELECT a.*, u.name AS doctor_name FROM appointments a JOIN doctor d ON a.doctor_id = d.id JOIN user u ON d.user_id = u.id WHERE a.patient_id = ? AND a.status = 'pending'";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "i", $patient_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    if (mysqli_num_rows($result) > 0) {
                        while ($appt = mysqli_fetch_assoc($result)) {
                            echo "<p><strong>Date:</strong> {$appt['date']}</p>";
                            echo "<p><strong>Doctor:</strong> {$appt['doctor_name']}</p>";
                            echo "<p><strong>Time:</strong> {$appt['time']}</p>";
                            echo "<hr>";
                        }
                    } else {
                        echo "<p>No upcoming appointments.</p>";
                    }
                    mysqli_stmt_close($stmt);
                    ?>
                </div>

                <div class="card" id="medical-records">
                    <h3>Medical Records</h3>
                    <p><strong>Condition:</strong> Hypertension</p>
                    <p><strong>Last Report:</strong> Blood Pressure - 140/90 mmHg</p>
                    <a href="#">View Full Report</a> | <a href="#">Upload New</a>
                </div>

                <div class="card" id="medications">
                    <h3>Current Medications</h3>
                    <p><strong>Medicine:</strong> Amlodipine 5mg</p>
                    <p><strong>Dosage:</strong> 1 tablet daily</p>
                    <button>Set Reminder</button>
                </div>

                <!-- Health Vitals with Graphs -->
                <div class="card" id="vitals">
                    <h3>Health Vitals</h3>
                    <?php if (isset($graph_error)) { ?>
                        <p class="error"><?php echo $graph_error; ?></p>
                    <?php } else { ?>
                        <h4>Appointments Over the Past 6 Months</h4>
                        <img src="graphs/appointments_<?php echo $patient_id; ?>.png" alt="Appointments Graph" class="graph">

                        <h4>Blood Pressure Trends</h4>
                        <img src="graphs/blood_pressure_<?php echo $patient_id; ?>.png" alt="Blood Pressure Graph" class="graph">

                        <h4>Sugar Level Trends</h4>
                        <img src="graphs/sugar_level_<?php echo $patient_id; ?>.png" alt="Sugar Level Graph" class="graph">

                        <h4>Appointment Status Breakdown</h4>
                        <img src="graphs/appointment_status_<?php echo $patient_id; ?>.png" alt="Appointment Status Graph" class="graph">
                    <?php } ?>
                    <button style="margin-top: 10px;">Add New Record</button>
                </div>

                <!-- Book Appointment -->
                <div class="card" id="book-appointment">
                    <h3>Book Appointment</h3>
                    <form method="POST" action="">
                        <label>Select Doctor:</label>
                        <select name="doctor_id" required>
                            <option value="">Choose a Doctor</option>
                            <?php
                            $query = "SELECT d.id, u.name, d.specialization FROM doctor d JOIN user u ON d.user_id = u.id WHERE u.status = 'approved'";
                            $result = mysqli_query($conn, $query);
                            while ($doc = mysqli_fetch_assoc($result)) {
                                echo "<option value='{$doc['id']}'>{$doc['name']} ({$doc['specialization']})</option>";
                            }
                            ?>
                        </select>
                        <label>Date:</label>
                        <input type="date" name="date" required>
                        <label>Time:</label>
                        <input type="time" name="time" required>
                        <input type="submit" name="book_appointment" value="Book Appointment">
                        <?php if (isset($booking_message)) echo "<p class='success'>$booking_message</p>"; ?>
                        <?php if (isset($booking_error)) echo "<p class='error'>$booking_error</p>"; ?>
                    </form>
                </div>
            <?php } else { ?>
                <!-- Doctor-Specific Sections -->
                <div class="card" id="patients">
                    <h3>My Patients</h3>
                    <table>
                        <tr><th>Name</th><th>Email</th><th>Medical History</th><th>Action</th></tr>
                        <?php
                        $doctor_id = $doctor_data["id"];
                        $query = "SELECT u.*, p.* FROM user u JOIN patients p ON u.id = p.user_id JOIN appointments a ON p.id = a.patient_id WHERE a.doctor_id = ? GROUP BY u.id LIMIT 5";
                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        while ($patient = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>{$patient['name']}</td>";
                            echo "<td>{$patient['email']}</td>";
                            echo "<td>{$patient['medical_history']}</td>";
                            echo "<td><a href='#'>View Profile</a></td>";
                            echo "</tr>";
                        }
                        mysqli_stmt_close($stmt);
                        ?>
                    </table>
                    <button style="margin-top: 10px;">View All Patients</button>
                </div>

                <div class="card" id="appointments">
                    <h3>Upcoming Appointments</h3>
                    <?php
                    $query = "SELECT a.*, u.name AS patient_name FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN user u ON p.user_id = u.id WHERE a.doctor_id = ? AND a.status = 'pending'";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    if (mysqli_num_rows($result) > 0) {
                        while ($appt = mysqli_fetch_assoc($result)) {
                            echo "<p><strong>Date:</strong> {$appt['date']}</p>";
                            echo "<p><strong>Patient:</strong> {$appt['patient_name']}</p>";
                            echo "<p><strong>Time:</strong> {$appt['time']}</p>";
                            echo "<hr>";
                        }
                    } else {
                        echo "<p>No upcoming appointments.</p>";
                    }
                    mysqli_stmt_close($stmt);
                    ?>
                </div>

                <div class="card" id="schedule">
                    <h3>My Schedule</h3>
                    <p>Manage your availability here.</p>
                    <button>Update Schedule</button>
                </div>

                <!-- Set Availability -->
                <div class="card" id="availability">
                    <h3>Set Availability</h3>
                    <form method="POST" action="">
                        <label>Date:</label>
                        <input type="date" name="date" required>
                        <label>Start Time:</label>
                        <input type="time" name="start_time" required>
                        <label>End Time:</label>
                        <input type="time" name="end_time" required>
                        <input type="submit" name="add_slot" value="Add Slot">
                        <?php if (isset($availability_error)) echo "<p class='error'>$availability_error</p>"; ?>
                    </form>
                    <h4>Current Availability</h4>
                    <table>
                        <tr><th>Date</th><th>Start Time</th><th>End Time</th></tr>
                        <?php
                        $query = "SELECT * FROM doctor_availability WHERE doctor_id = ? LIMIT 5";
                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, "i", $doctor_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        while ($slot = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>{$slot['date']}</td>";
                            echo "<td>{$slot['start_time']}</td>";
                            echo "<td>{$slot['end_time']}</td>";
                            echo "</tr>";
                        }
                        mysqli_stmt_close($stmt);
                        ?>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php mysqli_close($conn); ?>
</body>
</html>