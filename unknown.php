<?php

 $from_url=$_GET["from"];
 function redirect($url){
    header("Location: $url");
 }
 
 if( $from_url && strpos($from_url,"blog/index.php/20")){
  $post_url_main='https://yunusemrevurgun.com/blog/posts/';
  if(substr($from_url,-1)!="/"){
      $from_url=$from_url."/";
  }
  switch($from_url){
      case "https://yunusemrevurgun.com/blog/index.php/2024/03/04/dont-learn-to-code-use-natural-language-instead-watch-a-movie/": 
          
          redirect($post_url_main."Don---t-Learn-to-Code--use-Natural-Language-Instead------Watch-a-movie-or-something-.html");break;
          
      case "https://yunusemrevurgun.com/blog/index.php/2024/02/21/about-the-gen-ai-hype/":
          
          redirect($post_url_main."About-the-gen-AI-hype.html");break;
          
      case "https://yunusemrevurgun.com/blog/index.php/2024/02/16/my-take-on-cs50-matt-welsh-lecture/": 
    
          redirect($post_url_main."My-Take-on-CS---Matt-Welsh-Lecture.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2023/06/10/ml-algorithms-explained-1/": 
    
          redirect($post_url_main."ML-Algorithms-Explained---.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2023/06/09/song-lyrics-draft-1/": 
    
          redirect($post_url_main."Song-Lyrics-Draft--.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2023/06/09/how-do-i-compose/": 
    
          redirect($post_url_main."How-Do-I-Compose-.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2023/06/09/what-are-daos/": 
    
          redirect($post_url_main."What-are-DAOs-.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2023/06/09/testing-out-the-artificial-intelligence-of-midjourney-discord-server/": 
    
          redirect($post_url_main."Testing-out-the-Artificial-Intelligence-of-Midjourney-Discord-Server.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2023/06/09/how-to-write-a-java-program-that-takes-average/":
    
          redirect($post_url_main."How-to-Write-a-Java-Program-that-Takes-Average.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2023/06/09/how-does-php-run/": 
    
          redirect($post_url_main."How-Does-PHP-Run-.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2023/06/09/how-did-they-come-up-with-node-js-how-does-it-work/": 
    
          redirect($post_url_main."How-Did-They-Come-Up-With-Node-js---How-Does-it-Work-.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2023/06/09/differences-between-java-and-javascript-variables/": 
    
          redirect($post_url_main."Differences-Between-Java-and-JavaScript-Variables.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2023/06/09/chatgpt-for-finding-syntax-errors-in-your-code/": 
    
          redirect($post_url_main."ChatGPT-for-Finding-Syntax-Errors-in-Your-Code.html");break;
    
      case "https://yunusemrevurgun.com/blog/index.php/2022/06/09/why-is-this-site-on-blogger-and-not-on-a-cpanel-wp-linux-hosting-old-post-and-not-relevant-to-the-current-site/": 
    
          redirect($post_url_main."Why-is-This-Site-on-Blogger-and-not-on-a-Cpanel-WP-Linux-Hosting----old-post-and-not-relevant-to-the-current-site-.html");break;
          
          
  default:     
      redirect("https://yunusemrevurgun.com/blog/");break;

  }

 }else{
    header("Location: https://yunusemrevurgun.com/index/index.html");
 }


?>