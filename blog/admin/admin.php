<?php
// Step 1: Connect to the database
require "../../../YUNUSEMREVURGUN_DB/db.php";
 sleep(2);
 $servername =  $GLOBALS['servername_SQL'];
 $dbname =  $GLOBALS['dbname_SQL'];
 $username_for_db = $GLOBALS['username_SQL'];
 $password_for_db = $GLOBALS['password_SQL'];
$conn = new mysqli($servername, $username_for_db, $password_for_db, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function verify_hash($data, $hashed_data) {
    $new_hash = hash('sha256', $data);
    return $new_hash === $hashed_data;
  }
// Step 3: Validate the credentials
$logged_in = false;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
    $input_username = $_POST['username'];
    sleep(2);
$sanitized_input_username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
$sanitized_input_password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    
    if ($sanitized_input_username !== null && $sanitized_input_username !== false
&&  $sanitized_input_password !== null && $sanitized_input_password !== false
) {
    
    $input_password = hash('sha256', $_POST['password']); // Hash the entered password using SHA-256

    $sql = "SELECT username, password FROM admin";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        if ($row["username"] == $input_username && $row["password"] == $input_password) {
            $logged_in = true;
            break;
        }
    }
     
}else{
            echo "<script>alert('Login dangerously failed!'); window.location.href='https://yunusemrevurgun.com/';</script>";
            exit;

}
    if ($logged_in) {
        session_start();
 
        $_SESSION['logged_in']="JFJF84RYRJCUIDU3HJDIFUEJ333HDHDJDCDI833JAQAWSTGFKH";
        
        echo "<script>alert('Login successful!'); window.location.href='dashboard.php';</script>";
    } else {
        echo "<script>alert('Login failed!'); window.location.href='https://yunusemrevurgun.com/';</script>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>    <meta name="robots" content="noindex">

    <title>Admin Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
     <link rel=stylesheet href=styles/admin.css>
    <script src="scripts/admin.js"></script>
 </head>
<body>
    <?php if(!$logged_in): ?>
    <h2>Admin Login</h2>
    <form action="admin.php" method="post">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" maxlength="50" required><br>
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" maxlength="50" required><br><br>
        <input type="submit" value="Login">
    </form>
    <?php endif; ?>
    <script>
        document.addEventListener('DOMContentLoaded',function(){
            setTimeout(()=>{document.body.remove();window.location.href="about:blank"},8000);
        });
    </script>
</body>
</html>
