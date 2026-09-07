<?php
/** CLI-only render QA with an in-memory database. Never creates public content. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('MODE=development');putenv('DB_CONNECTION=sqlite');putenv('DB_NAME=:memory:');
$_SERVER['HTTP_HOST']='127.0.0.1:8817';$_SERVER['PHP_SELF']='/index.php';
require __DIR__.'/../../models/Music.php';
require __DIR__.'/../../models/Videos.php';
$db=Database::getInstance()->getConnection();
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)');
$db->exec('CREATE TABLE tracker_codes (id INTEGER PRIMARY KEY, code TEXT, is_active INTEGER)');
new Music();new Videos();
$db->exec("INSERT INTO music_tracks (filename,title,description,duration) VALUES ('qa-missing.wav','QA recording','A missing test file used to verify the playback error state.',60)");
$db->exec("INSERT INTO videos (title,description,type,platform,video_id) VALUES ('QA video','Test card for responsive video layout.','url','youtube','test')");
foreach(['music'=>'music.php','videos'=>'videos/index.php'] as $route=>$view){
    $_SERVER['REQUEST_URI']='/'.$route;
    ob_start();require __DIR__.'/../../views/'.$view;$html=ob_get_clean();
    $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($html);$xp=new DOMXPath($dom);
    if($xp->query('//h1')->length!==1)throw new RuntimeException('Heading regression');
    foreach($xp->query('//script[@type="application/ld+json"]') as $node)json_decode($node->textContent,true,512,JSON_THROW_ON_ERROR);
    if(!str_contains($html, 'id="'.($route==='music'?'track-':'video-').'1"'))throw new RuntimeException('Structured-data target missing');
    $dir=__DIR__.'/../_work';if(!is_dir($dir))mkdir($dir,0755,true);
    file_put_contents($dir.'/'.$route.'-preview.html',$html);
}
echo "6 populated media rendering checks passed\n";
