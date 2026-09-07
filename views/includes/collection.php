<?php
/** Shared public collection structure; IDs and structured data describe real entries. */
function ui_entry_id(string $title): string {
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
    return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii)), '-');
}
function ui_collection_schema(string $name, string $path, array $titles, array $anchors = []): string {
    $url = rtrim(FULL_BASE_PATH, '/') . '/' . $path;
    $list = array_map(fn($title, $index) => ['@type'=>'ListItem', 'position'=>$index+1, 'name'=>$title, 'url'=>$url.'#'.($anchors[$index] ?? ui_entry_id($title))], $titles, array_keys($titles));
    return '<script type="application/ld+json">' . json_encode(['@context'=>'https://schema.org', '@graph'=>[
        ['@type'=>'CollectionPage', '@id'=>$url, 'url'=>$url, 'name'=>$name, 'mainEntity'=>['@type'=>'ItemList', 'itemListElement'=>$list]],
        ['@type'=>'BreadcrumbList', 'itemListElement'=>[
            ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>rtrim(FULL_BASE_PATH, '/').'/'],
            ['@type'=>'ListItem','position'=>2,'name'=>$name,'item'=>$url],
        ]],
    ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
}
function ui_collection_tools(array $modules): void { ?>
    <details class="ui-collection-index"><summary>Browse the index</summary><ol>
        <?php foreach ($modules as $module): ?><li><a href="#<?= ui_entry_id($module['title']) ?>"><?= htmlspecialchars($module['title']) ?></a></li><?php endforeach; ?>
    </ol></details>
    <div class="ui-collection-tools">
        <div class="ui-collection-search"><label for="collectionSearch">Find an entry</label><input type="search" id="collectionSearch" data-collection-search placeholder="Search titles, topics, or text" autocomplete="off"></div>
        <p class="ui-collection-status" data-collection-status role="status" aria-live="polite"><?= count($modules) ?> entries</p>
    </div>
<?php }
