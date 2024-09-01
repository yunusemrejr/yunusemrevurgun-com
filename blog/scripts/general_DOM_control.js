//DELETES THE INDEX LINK FROM BLOG POSTS LIST, THE INDEX.HTML IS A PLACEHOLDER/REDIRECTOR SO IT SHOULDN'T BE LISTED
document.addEventListener('DOMContentLoaded', function() {
    if (!window.location.href.includes("blog/posts")) {
      document.querySelectorAll(".link-to-post").forEach(element => {
       if(element.textContent.trim() == "Index"){
        if (element.nextSibling.nextSibling && element.nextSibling.nextSibling.tagName === "HR") {
            element.nextSibling.nextSibling.remove();
          }    
          if (element.previousSibling.previousSibling && element.previousSibling.previousSibling.tagName === "I") {
            element.previousSibling.previousSibling.remove();
          }   
            element.remove();
            
       }  
      });
    }
    
    
    //REDIRECTION
       if(window.location.href.includes("blog/index.php/20")){
       window.location.href=`https://yunusemrevurgun.com/unknown.php?from=${window.location.href}`;
   }else if(window.location.href.includes("/index.php")){
       const updatedUrl = window.location.href.replace(/\/index\.php/g, '');

              window.location.href= updatedUrl;

   }

  //IMAGE FIX
const imgFix=()=>{
      // Get all image tags in the DOM
const images = document.querySelectorAll('img');

// Regular expression to match the specified pattern
const regex = /\/blog\/wp-content\/uploads\/\d{4}\/\d{2}\//;

// Iterate through each image tag
images.forEach(image => {
    // Get the source URL of the image
    let src = image.getAttribute('src');
    
    // Check if the source URL matches the pattern
    if (regex.test(src)) {
        // Replace the matching section of the URL
        src = src.replace(regex, '/media/blog/');
        
        // Update the src attribute of the image tag
        image.setAttribute('src', src);
        image.removeAttribute('srcset');
    }
});
};imgFix();



  });




  