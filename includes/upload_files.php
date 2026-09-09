<?php
/** Delete only regular files immediately inside the intended upload directory. */
function removeUploadFile(string $directory, ?string $filename): bool {
    if (!$filename || $filename !== basename($filename) || str_contains($filename, '\\') || str_contains($filename, "\0")) return false;
    $root = realpath($directory);
    if ($root === false) return false;
    $path = $root . DIRECTORY_SEPARATOR . $filename;
    if (is_link($path)) return false;
    if (!file_exists($path)) return true;
    if (!is_file($path) || dirname((string)realpath($path)) !== $root) return false;
    if (!unlink($path)) {
        error_log('Upload cleanup failed in ' . basename($root));
        return false;
    }
    return true;
}
