<?php
global $html_output;
$html_output = '';

// Directory where your HTML files are stored
$directory = 'posts/';
$files = glob($directory . '*.html');

function sortByLastModified($a, $b) {
    return filemtime($a) - filemtime($b);
}

// Sort the files array using the custom sorting function
usort($files, 'sortByLastModified');

// Reverse the order to have the newest files first
$files = array_reverse($files);
// Get all HTML files in the directory

// Number of items per page
$itemsPerPage = 5;

// Get current page number from query string
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Calculate starting point for fetching files
$start = ($page - 1) * $itemsPerPage;

// Get a slice of files for the current page
$filesOnPage = array_slice($files, $start, $itemsPerPage);

// Display files for the current page
foreach ($filesOnPage as $file) {
    $final_title=ucwords(str_replace('-',' ', basename($file)));
    $final_title= str_replace('.html',' ',$final_title);
    $html_output .= '<span class=span-before-link></span><i class="fa-solid fa-file-lines"></i> <a class="link-to-post" href="' . $file . '">' . $final_title . '</a><br><hr class=post-sep>';
}

// Pagination
$totalFiles = count($files);
$totalPages = ceil($totalFiles / $itemsPerPage);
$html_output .= '<br><div id=pages>';
for ($i = 1; $i <= $totalPages; $i++) {
    if ($i === $page) {
        $html_output .= ' ░'.$i . '░ ';
    } else {
        if($i<=5){
        $html_output .= '░<a href="?page=' . $i . '">░  ░' . $i . '░  </a> ';
        } 
    }
}
$next_pg =$page<=$totalPages-1 ? $page+1 : $next_pg=$totalPages;
$html_output .= '<a href="?page='.$next_pg.'"> ░Next░</a></div>';
?>


<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog | Yunus Emre Vurgun - Software Developer</title>
    <meta name="description" content="I am Yunus Emre Vurgun, a software developer building various applications in various technologies. 
    I create innovative solutions and explore new and old technologies. Has an interest in computer science, hardware, and artificial intelligence.">
    <script src="scripts/general.js"></script>
    <link rel="stylesheet" href="styles/general.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type='text/css' href="https://cdn.jsdelivr.net/gh/devicons/devicon@latest/devicon.min.css" />
    <link rel="icon" type="image/x-icon" href="../media/general/icon.webp">
  </head>
  <body>
    <header>
      <nav id="top-navbar">
        <img src="../media/general/icon.webp" alt="Yunus Emre Vurgun Logo">
        <ul>
          <li>
            <a href="../index/index.html">Home</a>
          </li>
          <li>
            <a href="../index/about.html">About /Projects</a>
          </li>
          <li>
            <a href="../index/gallery.html">Gallery</a>
          </li>
          <li>
            <a href="index.php">Blog</a>
          </li>
          <li>
            <a href="../wooden-log/index.php">Wooden-Log</a>
          </li>
        </ul>
      </nav>
      <h1>Yunus Emre Vurgun's Blog</h1>
      <nav >
        <ul>
          <li>
            <a href="#footer">Go to Bottom</a>
          </li>
           
        </ul>
      </nav>
    </header>
    <section id="blog-section">
      <h2>Blog Posts</h2>
      <div id="posts-container"><?php echo $html_output; ?></div>

    </section>
     
    <footer id='footer'>
      <p>&copy; 2024 Yunus Emre Vurgun - Software Developer</p>
    </footer>
    <script src="https://yunusemrevurgun.com/blog/scripts/general_DOM_control.js"></script>
<script>
        
    //REDIRECTION
       if(window.location.href.includes("blog/index.php/20")){
       window.location.href=`https://yunusemrevurgun.com/unknown.php?from=${window.location.href}`;
   }else if(window.location.href.includes("/index.php")){
       const updatedUrl = window.location.href.replace(/\/index\.php/g, '');

              window.location.href= updatedUrl;

   }
</script>
<!-- Start Open Web Analytics Tracker -->
<script type="text/javascript">
//<![CDATA[
var owa_baseUrl = 'https://gor.bio/analytics/';
var owa_cmds = owa_cmds || [];
owa_cmds.push(['setSiteId', 'f078cf651d6c4bd497f0a3bf53b4c5a9']);
owa_cmds.push(['trackPageView']);
owa_cmds.push(['trackClicks']);

(function() {
    var _owa = document.createElement('script'); _owa.type = 'text/javascript'; _owa.async = true;
    owa_baseUrl = ('https:' == document.location.protocol ? window.owa_baseSecUrl || owa_baseUrl.replace(/http:/, 'https:') : owa_baseUrl );
    _owa.src = owa_baseUrl + 'modules/base/dist/owa.tracker.js';
    var _owa_s = document.getElementsByTagName('script')[0]; _owa_s.parentNode.insertBefore(_owa, _owa_s);
}());
//]]>
</script>
<!-- End Open Web Analytics Code -->

        
  </body>
</html>