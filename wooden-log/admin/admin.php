<?php

  
session_start();
require "../../../YUNUSEMREVURGUN_DB/db.php";
 function verify_hash($data, $hashed_data) {
    $new_hash = hash('sha256', $data);
    return $new_hash === $hashed_data;
  }

function authenticate($username, $password) {
    $servername =  $GLOBALS['servername_SQL'];
    $dbname =  $GLOBALS['dbname_SQL'];
    $username_for_db = $GLOBALS['username_SQL'];
    $password_for_db = $GLOBALS['password_SQL'];
    // Use environment variables or a configuration file for credentials
    $conn = new mysqli($servername, $username_for_db, $password_for_db, $dbname);
  
    if($conn->connect_error){
      die('We are having technical difficulties. Please try again later.');
    }
    
    $sqlQuery = "SELECT username, password FROM admin";
    
    $result = $conn->query($sqlQuery);
  
    // Check if the query execution was successful (no errors)
    if ($result) {  
       if($result->num_rows > 0){
        $row = $result->fetch_assoc();  // Fetch only the first row
        $username_from_DB = $row['username'];
        $storedHashedPassword = $row['password'];
        if(verify_hash($password, $storedHashedPassword) && $username === $username_from_DB){
        
             return true;
         
        }else{
          echo "Invalid credentials. Please try again.<br>";
          if(verify_hash($password, $storedHashedPassword)){
            echo "accepting credentials.";
          }else{
            echo "Invalid credentials.";
          }
        }
        $conn->close();
      }
    } else {
        echo "no result from sql<br>";
    }
    
    echo "<script>window.location.href = '../index.php'</script>";
    header('Location: ../index.php');
    return false;
  }

// Function to establish a database connection (consider using a connection pool)
function establishConnection(){
    $servername =  $GLOBALS['servername_SQL'];
    $dbname =  $GLOBALS['dbname_SQL'];
    $username_for_db = $GLOBALS['username_SQL'];
    $password_for_db = $GLOBALS['password_SQL'];
    // Use environment variables or a configuration file for credentials
    $conn = new mysqli($servername, $username_for_db, $password_for_db, $dbname);

    if($conn->connect_error){
        die('We are having technical difficulties. Please try again later.');
    }

    return $conn;
}

// Function to add a new activity
function addActivity($date, $title, $body){
    $conn = establishConnection();

    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO logs (date, title, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $date, $title, $body);
    $result = $stmt->execute();

    if(!$result) {
        echo "<p>Failed to add activity: " . $conn->error . "</p>";
    } else {
        echo "<p>Activity added successfully!</p>";
    }

    $stmt->close();
    $conn->close();

    return $result;
}

// Function to delete an activity by ID
function deleteActivity($id){
    $conn = establishConnection();

    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("DELETE FROM logs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();

    if(!$result) {
        echo "<p>Failed to delete activity: " . $conn->error . "</p>";
    } else {
        echo "<p>Activity deleted successfully!</p>";
    }

    $stmt->close();
    $conn->close();

    return $result;
}

// Check if the login form is submitted
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (authenticate($username, $password)) {
        $_SESSION['username'] = $username;
        // Redirect to the authenticated section or continue execution
        header('Location: admin.php');
        exit;
    } else {
        $error = "Invalid credentials. Please try again.";
    }
}

// Check if the user is already logged in
if (isset($_SESSION['username'])) {
    // The user is already authenticated, continue with the rest of the code

?>

 


<!-- HTML code for the authenticated section -->
<!DOCTYPE html>
<html>
<head>
    <link rel='stylesheet' href='../styles/admin.css'>
    <title>Admin - Activity Stream</title>
    <style>
        /* Add your custom styles here */
    </style>
</head>
<body>
    <h1>Admin - Activity Stream</h1>

    <!-- Form for adding a new activity -->
    <h2>Add Activity</h2>
    <form method="POST" action="admin.php">
        <label for="title">Title:</label>
        <input type="text" name="title" id="title" required>
        <br>
        <label for="body">Body:</label>
        <textarea name="body" id="body" required></textarea>
        <br>
        <input type="submit" name="add" value="Add">
    </form>

    <!-- Form for deleting an activity -->
    <h2>Delete Activity</h2>
    <form method="POST" action="admin.php">
        <label for="id">Activity ID:</label>
        <input type="text" name="id" id="id" required>
        <br>
        <input type="submit" name="delete" value="Delete">
    </form>

    <?php
    // Check if the add form is submitted
    if(isset($_POST['add'])){
        $date = date('Y-m-d');
   
        
        $title = $_POST['title'];
        $body = $_POST['body'];

        $result = addActivity($date, $title, $body);
        if($result){
            echo "<p>Activity added successfully!</p>";
        }else{
            echo "<p>Failed to add activity.</p>";
        }
    }

    // Check if the delete form is submitted
    if(isset($_POST['delete'])){
        $id = $_POST['id'];

        $result = deleteActivity($id);
        if($result){
            echo "<p>Activity deleted successfully!</p>";
        }else{
            echo "<p>Failed to delete activity.</p>";
        }
    }
    ?>

</body>
</html>

<?php
} else {
    // The user is not logged in, show the login form
?>

<!-- HTML code for the login form -->
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>    <link rel='stylesheet' href='../styles/admin.css'>

</head>
<body>
    <h1>Login</h1>
    <?php if (isset($error)) {
        // "<p>$error</p>";
    } ?>
    <form method="POST" action="admin.php">
        <label for="username">Username:</label>
        <input type="text" name="username" id="username" required>
        <br>
        <label for="password">Password:</label>
        <input type="password" name="password" id="password" required>
        <br>
        <input type="submit" name="login" value="Login">
    </form>
</body>
</html>

<?php
}
?>
