<?php
// Get the document root
$docRoot = $_SERVER['DOCUMENT_ROOT'];

// Dynamically determine the project folder
$scriptPath = $_SERVER['SCRIPT_NAME'];
$projectFolder = '';

// Extract the project folder from the script path, excluding index.php
if (preg_match('~^(/[^/]+)(?=/index\.php|$)~', $scriptPath, $matches)) {
    $projectFolder = $matches[1];
}

// If we're not in a subfolder (direct in web root), use empty string
if ($projectFolder === '/' || $projectFolder === '/index.php') {
    $projectFolder = '';
}

// Include the setPath file using an absolute path
require_once $docRoot  . '/config/setPath.php';
 
// Get the document root
$docRoot = $_SERVER['DOCUMENT_ROOT'];

// Dynamically determine the project folder
$scriptPath = $_SERVER['SCRIPT_NAME'];
$projectFolder = '';

// Extract the project folder from the script path
if (preg_match('~^(/[^/]+)~', $scriptPath, $matches)) {
    $projectFolder = $matches[1];
}

// If we're not in a subfolder (direct in web root), use empty string
if ($projectFolder === '/') {
    $projectFolder = '';
}

// Build the base path
$basePath = $docRoot . $projectFolder;

// Return the base path
echo json_encode(['basePath' => $basePath]);
?>
