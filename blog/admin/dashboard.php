<?php
session_start();
sleep(2);

// Function to generate a secure session token
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

// Check if the session token is valid
if (!isset($_SESSION['session_token']) || $_SESSION['session_token'] !== $_SESSION['current_token']) {
    header('Location: admin.php');
    exit;
}

require "../../../YUNUSEMREVURGUN_DB/db.php";

// Session timeout and regeneration
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 300)) {
    // Session timed out, destroy session
    session_unset();
    session_destroy();
    header("Location: https://yunusemrevurgun.com/");
    exit;
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// Regenerate session ID if the session is old
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 150) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Handle POST request for creating a new post
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $article_body = filter_input(INPUT_POST, 'articleBody', FILTER_SANITIZE_STRING);

    // Generate URL slug from title
    $url_slug = preg_replace('/[^a-zA-Z]/', "-", $title);

    if ($title && $description && $article_body) {
        $file_template = "../posts/test.txt";
        $content_template = file_get_contents($file_template);

        if ($content_template !== false) {
            // Replace placeholders in the template with actual content
            $html_content = str_replace(["@title", "@description", "@article_body"], [$title, $description, $article_body], $content_template);

            // Create HTML file
            $html_file_name = "../posts/" . $url_slug . ".html";
            if (file_put_contents($html_file_name, $html_content) !== false) {
                sleep(2);
                header("Location: ../posts/$html_file_name");
                exit;
            } else {
                echo "Error: Unable to create HTML file.";
                exit;
            }
        } else {
            echo "<br>Error reading template file.<br>";
            exit;
        }
    } else {
        // Invalid POST data, destroy session and redirect
        session_unset();
        session_destroy();
        header("Location: https://yunusemrevurgun.com/");
        exit;
    }
}

// Generate clickable link and JavaScript for automatic click
$sitemap_maker_url = 'sitemap-maker.php';
$html_link = "<a id='clickablelink' href='$sitemap_maker_url' target='_blank'>CLICK HERE TO ADD TO SITEMAP NOW QUICK!</a>";
$js_click_link = "<script>document.getElementById('clickablelink').click();</script>";

echo $html_link;
echo $js_click_link;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <script src="scripts/dashboard.js"></script>
    <meta name="robots" content="noindex">
</head>
<body>
    <h2>Dashboard</h2>
    <p>Welcome to your dashboard. Here you can create new posts.</p>
    <button onclick="clearInputs()" id="clearInputs">Clear input fields</button>
    <textarea id="bulk_area"></textarea>
    <button onclick="formatBulkText(document.getElementById('bulk_area').value)" id="bulk_area_button">Apply to fields format</button>
    
    <!-- Post creation form -->
    <form action="dashboard.php" method="post">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" maxlength="300" required><br>
        
        <label for="description">Description:</label><br>
        <textarea id="description" name="description" maxlength="1000" required></textarea><br>
        
        <label for="articleBody">Article Body:</label><br>
        <textarea id="articleBody" name="articleBody" maxlength="20000" required></textarea><br><br>
        
        <input type="submit" value="Create Post">
    </form>
    
    <!-- Log out link -->
    <p><a href="logout.php">Log out</a></p>
    
    <script>
        function clearInputs() {
            document.querySelector('#title').value = "";
            document.querySelectorAll('textarea').forEach(field => field.value = "");
        }

        function formatBulkText(bulk) {
            const titlePart = bulk.substring(bulk.indexOf("Title:") + 6, bulk.indexOf("Description:")).trimStart();
            const descriptionPart = bulk.substring(bulk.indexOf("Description:") + 12, bulk.indexOf("<p>")).trimStart();
            const bodyPart = bulk.substring(bulk.indexOf("<p>")).trimStart();

            document.querySelector("#title").value = titlePart;
            document.querySelector("#description").value = descriptionPart;
            document.querySelector("#articleBody").value = bodyPart;
        }
    </script>
</body>
</html>
