<?php
session_start();
sleep(1);

// Function to generate a secure session token
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

// Check if the session token is valid
if (!isset($_SESSION['session_token']) || $_SESSION['session_token'] !== $_SESSION['current_token']) {
    session_unset();  // Unset $_SESSION variables
    session_destroy(); // Destroy session data
    header('Location: admin.php'); // Redirect to login page
    exit;
}

// Define paths and folder
$sitemap_file_path_and_name = "../sitemap/sitemap.xml";
$folder = "../posts";

// Check if the folder exists
if (is_dir($folder)) {
    // Initialize an empty array to store HTML files
    $htmlFiles = [];

    // Get the list of files in the folder
    $files = scandir($folder);

    // Iterate over the files
    foreach ($files as $file) {
        // Skip . and .. directories
        if ($file !== "." && $file !== "..") {
            // Check if it's a file (not a directory) and ends with .html
            if (is_file($folder . "/" . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'html') {
                // Add the HTML file to the array
                $htmlFiles[] = $file;
            }
        }
    }

    // Open sitemap file for writing
    $file_xml = fopen($sitemap_file_path_and_name, "w");
    if ($file_xml) {
        // Write the XML header
        fwrite($file_xml, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
        // Write the opening tag for urlset
        fwrite($file_xml, "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n");

        if ($htmlFiles) {
            foreach ($htmlFiles as $htmlFile) {
                // Write the URL and file name tags for each HTML file
                fwrite($file_xml, "\t<url>\n");
                fwrite($file_xml, "\t\t<loc>" . htmlspecialchars("https://yunusemrevurgun.com/blog/posts/" . $htmlFile) . "</loc>\n");
                fwrite($file_xml, "\t</url>\n");
            }
        }

        // Write the closing tag for urlset
        fwrite($file_xml, "</urlset>");

        // Close the XML file
        fclose($file_xml);

        echo '<script>window.close();</script>';
        exit;
    } else {
        echo "Failed to open sitemap file for writing.";
        echo '<script>window.close();</script>';
        exit;
    }
} else {
    echo "The specified folder does not exist.";
    header('Location: admin.php');
    exit;
}
?>
