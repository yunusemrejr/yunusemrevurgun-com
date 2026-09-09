<?php
ini_set('session.save_path', sys_get_temp_dir());
putenv('MODE=development');
putenv('DB_CONNECTION=sqlite');
putenv('DB_NAME=:memory:');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
require_once __DIR__ . '/../../global.php';
require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Gallery.php';
require_once __DIR__ . '/../../models/Travel.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';
require_once __DIR__ . '/../../includes/upload_files.php';
$count = 0;
function verify($ok, $label) { global $count; if (!$ok) throw new RuntimeException($label); $count++; }
$token = CSRFProtection::generateToken();
foreach ([[], ['token'], null, 12, '', str_repeat('x',64)] as $bad) verify(!CSRFProtection::validateToken($bad), 'Malformed CSRF rejected');
verify(CSRFProtection::validateToken($token), 'Existing tab token accepted');
$_SERVER['REMOTE_ADDR'] = '8.8.8.8';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '1.1.1.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '9.9.9.9';
verify(RateLimiter::getClientIP() === '8.8.8.8', 'Forged proxy headers ignored');
foreach (['104.16.1.2', '2606:4700::1'] as $peer) {
    $_SERVER['REMOTE_ADDR'] = $peer;
    verify(RateLimiter::getClientIP() === '1.1.1.1', 'Cloudflare client preserved');
}
$_SESSION = ['user_id'=>1];
verify(!Auth::checkLogin(false), 'Partial session rejected');
$_SESSION = ['user_id'=>1,'admin_logged_in'=>true,'last_activity'=>time(),'admin_user_agent'=>Auth::uaFingerprint('')];
verify(Auth::checkLogin(false), 'Normal session accepted');
$gallery = new Gallery(); $travel = new Travel();
$db = Database::getInstance()->getConnection();
$db->exec("INSERT INTO travel_locations (id,country,city,lat,lng) VALUES (1,'Test','Test',1,2)");
$db->exec("INSERT INTO gallery_images (id,filename,title) VALUES (1,'qa-missing.jpg','Test')");
verify($travel->addGalleryImageRef(1,1,'../../fake'), 'Link accepted from database');
verify($travel->addGalleryImageRef(1,1,'ignored'), 'Duplicate link idempotent');
verify(count($travel->getImagesByLocation(1)) === 1, 'No duplicate image');
verify($travel->getImagesByLocation(1)[0]['filename'] === 'qa-missing.jpg', 'Filename is authoritative');
verify(!$travel->addGalleryImageRef(999,1,'ignored'), 'Missing location rejected');
$db->exec("CREATE TRIGGER fail_gallery_delete BEFORE DELETE ON gallery_images BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
try { $gallery->deleteImage(1); verify(false, 'Deletion should fail'); } catch (PDOException $expected) {}
verify(count($travel->getImagesByLocation(1)) === 1 && $gallery->getImageById(1), 'Failed deletion rolls back both sides');
$db->exec('DROP TRIGGER fail_gallery_delete');
verify($gallery->deleteImage(1), 'Gallery deletion succeeds');
verify(count($travel->getImagesByLocation(1)) === 0, 'Travel reference removed');
verify($travel->deleteLocation(1), 'Location deletion succeeds');
verify(!$travel->deleteLocation(1), 'Repeated deletion reports missing');
$dir = sys_get_temp_dir() . '/yev-file-qa-' . bin2hex(random_bytes(5)); mkdir($dir); file_put_contents($dir.'/photo.jpg','fixture');
verify(!removeUploadFile($dir,'../photo.jpg'), 'Traversal rejected');
verify(!removeUploadFile($dir,'..\\photo.jpg'), 'Backslash rejected');
symlink($dir.'/photo.jpg',$dir.'/link.jpg');
verify(!removeUploadFile($dir,'link.jpg'), 'Symlink rejected');
unlink($dir.'/link.jpg'); verify(removeUploadFile($dir,'photo.jpg'), 'Normal cleanup succeeds'); rmdir($dir);
$_SESSION['last_activity'] = time()-3601;
verify(!Auth::checkLogin(false) && empty($_SESSION), 'Expired session cleared immediately');
echo "PASS: $count admin security and relationship checks\n";
