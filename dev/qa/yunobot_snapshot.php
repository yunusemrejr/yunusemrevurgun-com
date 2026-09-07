<?php
putenv('MODE=development');putenv('DB_CONNECTION=sqlite');putenv('DB_NAME=:memory:');
$_SERVER['HTTP_HOST']='localhost';$_SERVER['PHP_SELF']='/index.php';
require __DIR__.'/../../models/YunoBotKnowledge.php';
$db = Database::getInstance()->getConnection();
$db->exec('CREATE TABLE blog_posts (title TEXT, slug TEXT, content TEXT, updated_at TEXT, created_at TEXT, status TEXT)');
$stmt=$db->prepare('INSERT INTO blog_posts VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute(['Public article','public','<p>A public paragraph containing useful project details and Unicode: Öğrenme.</p><script>alert(1)</script>','2026-09-08','2026-09-08','published']);
$stmt->execute(['Secret draft','draft','Private unpublished material is never part of the public source index.','2026-09-08','2026-09-08','draft']);
$snapshot=(new YunoBotKnowledge($db))->snapshot();
if(count($snapshot['publishedUrls'])!==1||count($snapshot['passages'])!==1)throw new RuntimeException('Public-only selection failed');
if(str_contains(json_encode($snapshot),'Secret')||str_contains(json_encode($snapshot),'alert'))throw new RuntimeException('Unsafe/private content exposed');
if(!str_contains($snapshot['passages'][0]['text'],'Öğrenme'))throw new RuntimeException('Unicode lost');
$db->exec("UPDATE blog_posts SET status='draft'");
if((new YunoBotKnowledge($db))->snapshot()['publishedUrls']!==[])throw new RuntimeException('Unpublished article retained');
echo "4 public source snapshot checks passed\n";
