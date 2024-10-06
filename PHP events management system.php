<?php
session_start();

// Define file paths
$registrations_file = 'registrations.csv';
$events_file = 'events.csv';
$banned_file = 'banned.csv';

// Define admin credentials
define('ADMIN_USERNAME', 'Yasin1122');
define('ADMIN_PASSWORD', 'password123'); // Change this to a secure password

// Get client IP
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else{
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Check if IP is banned
function is_banned($ip) {
    global $banned_file;
    if (!file_exists($banned_file)) {
        return false;
    }
    $banned = array_map('str_getcsv', file($banned_file));
    foreach ($banned as $entry) {
        if ($entry[0] === $ip) {
            return true;
        }
    }
    return false;
}

// Ban IP
function ban_ip($ip) {
    global $banned_file;
    $file = fopen($banned_file, 'a');
    fputcsv($file, array($ip, date('Y-m-d H:i:s')));
    fclose($file);
}

// Initialize events.csv if not exists
if (!file_exists($events_file)) {
    $default_events = array(
        array('Event ID', 'Event Name', 'Description'),
        array('1', 'Conference', 'Annual tech conference'),
        array('2', 'Workshop', 'Hands-on workshop'),
        array('3', 'Seminar', 'Educational seminar')
    );
    $file = fopen($events_file, 'w');
    foreach ($default_events as $event) {
        fputcsv($file, $event);
    }
    fclose($file);
}

// Handle Registration Form Submission
$registration_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Sanitize inputs
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $event = htmlspecialchars(trim($_POST['event']));
    $comments = htmlspecialchars(trim($_POST['comments']));

    // Server-side validation
    if (empty($name) || empty($email) || empty($phone) || empty($event)) {
        $registration_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registration_message = "Invalid email format.";
    } elseif (!preg_match('/^\d{10,15}$/', $phone)) {
        $registration_message = "Invalid phone number format.";
    } else {
        // Save to CSV
        $file = fopen($registrations_file, 'a');
        // If file is empty, add headers
        if (filesize($registrations_file) == 0) {
            fputcsv($file, array('Name', 'Email', 'Phone', 'Event', 'Comments', 'Registration Time'));
        }
        fputcsv($file, array($name, $email, $phone, $event, $comments, date('Y-m-d H:i:s')));
        fclose($file);
        $registration_message = "Registration successful!";
    }
}

// Handle Admin Login
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $ip = get_client_ip();

    if (is_banned($ip)) {
        $login_error = "Your IP has been banned due to multiple failed login attempts.";
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['login_attempts'] = 0;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            // Increment login attempts
            if (!isset($_SESSION['login_attempts'])) {
                $_SESSION['login_attempts'] = 1;
            } else {
                $_SESSION['login_attempts'] += 1;
            }

            if ($_SESSION['login_attempts'] >= 3) {
                ban_ip($ip);
                $login_error = "Too many failed attempts. Your IP has been banned.";
            } else {
                $login_error = "Invalid credentials. Attempt " . $_SESSION['login_attempts'] . " of 3.";
            }
        }
    }
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle Export Registrations
if (isset($_GET['action']) && $_GET['action'] === 'export' && isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=registrations.csv');
    readfile($registrations_file);
    exit();
}

// Handle CRUD Operations for Events
$crud_message = '';
if (isset($_POST['crud'])) {
    if (isset($_POST['add_event'])) {
        // Add Event
        $new_event_id = uniqid();
        $new_event_name = htmlspecialchars(trim($_POST['event_name']));
        $new_event_desc = htmlspecialchars(trim($_POST['event_description']));

        if (!empty($new_event_name) && !empty($new_event_desc)) {
            $file = fopen($events_file, 'a');
            fputcsv($file, array($new_event_id, $new_event_name, $new_event_desc));
            fclose($file);
            $crud_message = "Event added successfully.";
        } else {
            $crud_message = "All event fields are required.";
        }
    } elseif (isset($_POST['edit_event'])) {
        // Edit Event
        $edit_id = $_POST['event_id'];
        $edit_name = htmlspecialchars(trim($_POST['event_name']));
        $edit_desc = htmlspecialchars(trim($_POST['event_description']));

        if (!empty($edit_name) && !empty($edit_desc)) {
            $events = array_map('str_getcsv', file($events_file));
            $file = fopen($events_file, 'w');
            foreach ($events as $event) {
                if ($event[0] === $edit_id) {
                    fputcsv($file, array($edit_id, $edit_name, $edit_desc));
                } else {
                    fputcsv($file, $event);
                }
            }
            fclose($file);
            $crud_message = "Event updated successfully.";
        } else {
            $crud_message = "All event fields are required.";
        }
    } elseif (isset($_POST['delete_event'])) {
        // Delete Event
        $delete_id = $_POST['event_id'];

        $events = array_map('str_getcsv', file($events_file));
        $file = fopen($events_file, 'w');
        foreach ($events as $event) {
            if ($event[0] !== $delete_id && $event[0] !== 'Event ID') { // Keep header
                fputcsv($file, $event);
            }
        }
        fclose($file);
        $crud_message = "Event deleted successfully.";
    }
}

// Fetch Events
$events = array();
if (($handle = fopen($events_file, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $events[] = $data;
    }
    fclose($handle);
}

// Fetch Registrations
$registrations = array();
if (file_exists($registrations_file)) {
    if (($handle = fopen($registrations_file, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $registrations[] = $data;
        }
        fclose($handle);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Registration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
            padding: 20px;
        }
        .container {
            width: 800px;
            margin: auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
        }
        h2 {
            text-align: center;
        }
        form {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input[type=text], input[type=email], input[type=tel], select, textarea, input[type=password] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        button, input[type=submit] {
            padding: 10px 15px;
            margin-top: 15px;
            border: none;
            background-color: #007BFF;
            color: white;
            cursor: pointer;
            border-radius: 3px;
        }
        button:hover, input[type=submit]:hover {
            background-color: #0056b3;
        }
        .message {
            color: green;
            margin-top: 10px;
        }
        .error {
            color: red;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top:20px;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        .admin-section {
            margin-top: 30px;
        }
        .crud-section {
            margin-top: 20px;
        }
        .crud-section table tr:hover {
            background-color: #f1f1f1;
        }
        .logout {
            text-align: right;
            margin-top: -40px;
        }
    </style>
    <script>
        // Client-side Validation for Registration Form
        function validateRegistrationForm() {
            var name = document.getElementById("name").value.trim();
            var email = document.getElementById("email").value.trim();
            var phone = document.getElementById("phone").value.trim();
            var event = document.getElementById("event").value.trim();
            if (name === "" || email === "" || phone === "" || event === "") {
                alert("Please fill in all required fields.");
                return false;
            }
            // Simple email format validation
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                alert("Please enter a valid email address.");
                return false;
            }
            // Simple phone number validation (10-15 digits)
            var phonePattern = /^\d{10,15}$/;
            if (!phonePattern.test(phone)) {
                alert("Please enter a valid phone number (10-15 digits).");
                return false;
            }
            return true;
        }

        // Edit Event Modal
        function editEvent(id, name, description) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_event_name').value = name;
            document.getElementById('edit_event_description').value = description;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Delete Event Confirmation
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this event?")) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Event Registration Form</h2>
        <?php
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            echo '<div class="logout"><a href="?action=logout">Logout</a></div>';
            echo '<div class="admin-section">';
            echo '<h3>Admin Dashboard</h3>';
            if (!empty($crud_message)) {
                echo '<div class="message">'.$crud_message.'</div>';
            }
            // Export Button
            echo '<a href="?action=export"><button>Export Registrations to CSV</button></a>';

            // Display Registrations
            echo '<h4>Registrations:</h4>';
            if (count($registrations) > 0) {
                echo '<table>';
                foreach ($registrations as $index => $reg) {
                    if ($index === 0) {
                        echo '<tr>';
                        foreach ($reg as $header) {
                            echo '<th>'.htmlspecialchars($header).'</th>';
                        }
                        echo '</tr>';
                    } else {
                        echo '<tr>';
                        foreach ($reg as $field) {
                            echo '<td>'.htmlspecialchars($field).'</td>';
                        }
                        echo '</tr>';
                    }
                }
                echo '</table>';
            } else {
                echo '<p>No registrations yet.</p>';
            }

            // CRUD Operations for Events
            echo '<div class="crud-section">';
            echo '<h4>Manage Events:</h4>';

            // Display Events
            if (count($events) > 1) { // Exclude header
                echo '<table>';
                echo '<tr><th>ID</th><th>Name</th><th>Description</th><th>Actions</th></tr>';
                foreach ($events as $index => $event) {
                    if ($index === 0) continue; // Skip header
                    echo '<tr>';
                    echo '<td>'.htmlspecialchars($event[0]).'</td>';
                    echo '<td>'.htmlspecialchars($event[1]).'</td>';
                    echo '<td>'.htmlspecialchars($event[2]).'</td>';
                    echo '<td>
                            <button onclick="editEvent(\''.htmlspecialchars($event[0]).'\', \''.htmlspecialchars(addslashes($event[1])).'\', \''.htmlspecialchars(addslashes($event[2])).'\')">Edit</button>
                            <button onclick="confirmDelete(\''.htmlspecialchars($event[0]).'\')">Delete</button>
                          </td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No events available.</p>';
            }

            // Add Event Form
            echo '<h4>Add New Event:</h4>';
            echo '<form method="POST">';
            echo '<label for="event_name">Event Name:</label>';
            echo '<input type="text" id="event_name" name="event_name" required>';
            echo '<label for="event_description">Description:</label>';
            echo '<textarea id="event_description" name="event_description" required></textarea>';
            echo '<input type="submit" name="crud" value="Add Event" onclick="this.form.add_event=true;">';
            echo '</form>';

            // Edit Event Modal
            echo '<div id="editModal" style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%); background-color:white; padding:20px; border:1px solid #ccc; border-radius:5px;">
                    <h4>Edit Event</h4>
                    <form method="POST">
                        <input type="hidden" id="edit_id" name="event_id">
                        <label for="edit_event_name">Event Name:</label>
                        <input type="text" id="edit_event_name" name="event_name" required>
                        <label for="edit_event_description">Description:</label>
                        <textarea id="edit_event_description" name="event_description" required></textarea>
                        <input type="submit" name="crud" value="Edit Event" onclick="this.form.edit_event=true;">
                        <button type="button" onclick="closeEditModal()">Cancel</button>
                    </form>
                  </div>';

            // Delete Event Form (Hidden)
            echo '<form method="POST" id="deleteForm" style="display:none;">
                    <input type="hidden" name="event_id" id="delete_id">
                    <input type="submit" name="crud" value="Delete Event">
                  </form>';

            echo '</div>'; // End of crud-section
            echo '</div>'; // End of admin-section
        } else {
            // Display Login Form
            echo '<h3>Admin Login</h3>';
            if (!empty($login_error)) {
                echo '<div class="error">'.$login_error.'</div>';
            }
            echo '<form method="POST">';
            echo '<label for="username">Username:</label>';
            echo '<input type="text" id="username" name="username" required>';
            echo '<label for="password">Password:</label>';
            echo '<input type="password" id="password" name="password" required>';
            echo '<input type="submit" name="login" value="Login">';
            echo '</form>';
        }

        // Registration Form
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo '<h3>User Registration</h3>';
            if (!empty($registration_message)) {
                if ($registration_message === "Registration successful!") {
                    echo '<div class="message">'.$registration_message.'</div>';
                } else {
                    echo '<div class="error">'.$registration_message.'</div>';
                }
            }
            echo '<form method="POST" onsubmit="return validateRegistrationForm();">';
            echo '<label for="name">Name:</label>';
            echo '<input type="text" id="name" name="name" required>';

            echo '<label for="email">Email:</label>';
            echo '<input type="email" id="email" name="email" required>';

            echo '<label for="phone">Phone Number:</label>';
            echo '<input type="tel" id="phone" name="phone" required>';

            echo '<label for="event">Event:</label>';
            echo '<select id="event" name="event" required>';
            echo '<option value="">Select an event</option>';
            foreach ($events as $index => $event) {
                if ($index === 0) continue; // Skip header
                echo '<option value="'.htmlspecialchars($event[1]).'">'.htmlspecialchars($event[1]).'</option>';
            }
            echo '</select>';

            echo '<label for="comments">Comments:</label>';
            echo '<textarea id="comments" name="comments"></textarea>';

            echo '<input type="submit" name="register" value="Register">';
            echo '</form>';
        }
        ?>
    </div>
</body>
</html>
