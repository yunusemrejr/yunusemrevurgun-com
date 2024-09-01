<?php
 require "../../YUNUSEMREVURGUN_DB/db.php";
 

//default page index is 1 and default post num.per page is 20 so because of this
//the getActivityLogs function sets var page to 1 and var perpage to 20.
function getActivityLogs($page = 1, $perPage = 20){
    $servername =  $GLOBALS['servername_SQL'];
    $dbname =  $GLOBALS['dbname_SQL'];
    $username = $GLOBALS['username_SQL'];
    $password = $GLOBALS['password_SQL'];

//the connection object is declared as a new mysqli instance where the credentials needed are defined as variables above
    $conn = new mysqli($servername, $username, $password, $dbname);

//this if statement makes sure if $conn results with an error of any type regarding the connection, the execution will terminate and a message will echo to the screen.
    if($conn->connect_error){
        die('we are having technical difficulties, please come back later');
    }

//$offset is important here because we know that the index of pages start from 0 in an array and so the current selected page will actually be x-1 to be exact and we multiply it with our limit per page to say, 'start getting logs from page x-1 times pages we have'
    $offset = ($page - 1) * $perPage;
    
    //this is the SQL query that selects columns of the sql table that contains our activity logs. selects these columns from the activitylogs table and orders them by date as descending items and makes sure the descending action takes place only for the selected portion aka the selected page elements of the total data table.
    
    $sqlQuery = "SELECT id, title, message, date FROM logs ORDER BY date DESC, date DESC LIMIT $perPage OFFSET $offset";
    
    //result object stores the result of connection being made and running the query above in MySQL server.
    
    $result = $conn->query($sqlQuery);

//below, if number of rows in the result object is greater than zero, an array variable named rows is declared. In the while loop, the row object is declared as $result->fetch_assoc() , which means fetched rows from the results object. While they keep getting discovered, $rows array will have them added to it. After the while scope ends, we close the connection and return the array of rows. 
    if($result->num_rows > 0){
        $rows = array();
        while($row = $result->fetch_assoc()){
            $rows[] = $row;
        }
        $conn->close();
        return $rows;
    }
    
    //on the other hand, the else statement here says if there are no rows out there, close the connection and echo it out.
    
    else{
        $conn->close();
        echo "Nothing here.";
    }
}

//the $page object here is declared as $_GET['page'] if $_GET['page'] is set. If it is not set, then it is equal to 1. This means if the page we are on is set to lets say page 4, then it is what it is, but if not, start from page 1 where the most recent posts appear at top.

$page = isset($_GET['page']) ? $_GET['page'] : 1;

//20 posts per page
$perPage = 10;

//declared as the function call with parameters above
$activityLogs = getActivityLogs($page, $perPage);

//MySQL connection credentials declared here.
$servername =  $GLOBALS['servername_SQL'];
$dbname =  $GLOBALS['dbname_SQL'];
$username = $GLOBALS['username_SQL'];
$password = $GLOBALS['password_SQL'];

//$conn object is declared as a new mysqli instance with above parameters
$conn = new mysqli($servername, $username, $password, $dbname);

//in case of a connection error, stop the execution and give a message to user.
if ($conn->connect_error) {
    die('we are having technical difficulties, please come back later');
}
 
//the SQL query to be executed to get the total log count from the table
$sqlQuery = "SELECT COUNT(*) AS totalLogs FROM logs";

//result object is $conn applied with the query
$result = mysqli_query($conn, $sqlQuery);
if (!$result) {
   // die('Error executing query: ' . mysqli_error($conn));
   die("err");
}
//the result object's totalLogs column extracted
$row = mysqli_fetch_assoc($result);
if ($row) {
    $totalLogs = $row['totalLogs'];
} else {
    $totalLogs = 0;
}
//connection closed
$conn->close();

//total pages declared as rounded number of total log count divided by logs allowed per page
$totalPages = ceil($totalLogs / $perPage);
?>


<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wooden-Log (Short Messages Board) | Yunus Emre Vurgun - Software Developer</title>
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
            <a href="../blog/index.php">Blog</a>
          </li>
          <li>
            <a href="../wooden-log/index.php">Wooden-Log</a>
          </li>
        </ul>
      </nav>
      <h1>Yunus Emre Vurgun's Wooden-Log (Short Messages Board)</h1>
      <nav >
        <ul>
          <li>
            <a href="#footer">Go to Bottom</a>
          </li>
           
        </ul>
      </nav>
    </header>
    <section id="log-section">
      <h2>Logs (from most recent to oldest)</h2>
      <div id="log-container"></div>
      <?php
    //for each of the logs (nicknamed as $e), use the REGEX pattern which checks for http(s) followed by :// and with www. or not and not case sensative, replace the matches with an HTML anchor tag here the href attribute will be set as the match and inner text of it is also the match itself, so that we have a clickable url instead of a plain text if the user types a url as an input
    
    foreach ($activityLogs as $e) {
$pattern = '/\b(?:https?:\/\/|www\.)\S+\b/i';
$replacement = '<a href="$0">$0</a>';

//here we take action and say if the element in question's body part (body column in sql) contain a match (checked and changed by preg_replace function with pattern and anchor and body passed as parameters) replace it.

$e['message'] = preg_replace($pattern, $replacement, $e['message']);

//echo an HTML div out, echo <span> elements in it for each column, assign elements date, time , title and body columns as values, echo the end of div.
        echo "<div class='log'>";
        echo "<span class='date'>[<i>" . $e['date'] . "</i>]  </span> &nbsp;░ ";
         echo "<span class='title'><b>" . $e['title'] . "</b> </span> &nbsp;░ ";
        echo "<span class='body'>" . $e['message'] . " </span>";
        echo "</div>";
    }
    ?>
    <!--from here forward, we have HTML and PHP more merged-->
        <!--we create the pagination div to contain page navigation-->

    <div class="pagination">
            <!--the PHP below checks for a current page number greater than 1-->
    <!--if it finds it, the Prev. button will have a value of current pg minus 1-->

        <?php if ($page > 1): ?>
            <a href="?page=<?php echo ($page - 1); ?>">░Previous░</a>
        <?php endif; ?>
        
            <!--below, I start a for loop that increases the $i value to max page count until it faces a situation where the current page is equal to $i, which helps us find the current page and make it show to the user-->

        <?php for ($i = 1; $i <= $totalPages; $i++):  
              if ($i == $page): ?>
                <span class="current-page">░<?php echo $i; ?>░</span>
            <?php else: ?>
            
                <!--if not the current page, echo it as just another page-->

                <a href="?page=<?php echo $i; ?>">░<?php echo $i; ?>░</a>
            <?php endif;  
          endfor; ?>
        
            <!--are we on the last page? if not lets have a next button to navigate further-->

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo ($page + 1); ?>">░Next░</a>
        <?php endif; ?>
    </div>
    </section>
     
    <footer id='footer'>
      <p>&copy; 2024 Yunus Emre Vurgun - Software Developer</p>
    </footer><script>
        
    //REDIRECTION
       if(window.location.href.includes("blog/index.php/20")){
       window.location.href=`https://yunusemrevurgun.com/unknown.php?from=${window.location.href}`;
   }else if(window.location.href.includes("/index.php")){
       const updatedUrl = window.location.href.replace(/\/index\.php/g, '');

              window.location.href= updatedUrl;

   }
</script>
  </body>
</html>