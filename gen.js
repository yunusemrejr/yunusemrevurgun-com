 
           
  let ourpostHeaderElement = document.getElementById('containerX');
   let descOfProg = "<p style='color:black;text-align:center'>E-mail Address Idea Generator is Web Tool created by Yunus Emre Vurgun. This tool lets you generate unlimited ideas about what your new E-mail address should look like!</p><br/><br/>";
  

  
  let HTMLForm = " <form id='form1' action='#'> <label for='favName'>Your Favorite Name: </label><br/><br/><input type='text' id='favName' name='favName'><br/><br/> <br/><label for='provider'>Your E-mail Provider (gmail / hotmail)</label><br> <br/><input type='text' id='provider' name='provider'> <br/><br/><br/><input type='submit' value='Generate' onclick='generator()' id='buttonGen'></form><p id='warnArea'></p> ";

  let results='%UD%';
  
  let resultBox = "<br><br><br><br><center><div id='resultBox' style='width:50%;border:2px solid white;border-radius:5px;'>Result:<br><br>"+results+"</div></center><br><br>";

  
ourpostHeaderElement.innerHTML='<div style="padding-top:1vw;color: black; text-align: center; "> <h1 style="color:black; " class="post-title"> &nbsp;E-mail Address Idea Generator</h1>'+descOfProg+HTMLForm+resultBox+'</div>';
  
  

  function form_prevent_default(x) {
      
      event.preventDefault();
      } 
      
document.getElementById('form1').addEventListener('submit', form_prevent_default);
 
  
$('#form1').submit(function(e){e.preventDefault()});
  
  
  document.getElementById('resultBox').style.display='none';

  
    function generator(){
          event.preventDefault();

    let favNameAreaID =document.getElementById('favName');
    let providerAreaID =document.getElementById('provider');
      
     let favNameValue = favNameAreaID.value;
      let providerAreaValue = providerAreaID.value;
      
       let result=null;
      
      let elseList =false;
      
      if(favNameValue.includes(" ")||providerAreaValue.includes(" ")){
        elseList=true;
      }
      
      if(favNameValue.length===0|| providerAreaValue.length===0 || favNameValue.length>30|| providerAreaValue.length>30 || elseList===true){
        
        
        alert('Please enter values that don\'t have spaces, less than 30 characters, and don\'t leave any field empty.');
        window.location.href='https://tools.yunusemrevurgun.com/p/e-mail-address-idea-generator.html';
        
      }else{
        
       let randomWord = [
                         
                       
"Aardvark",
"Aardwolf",
"Abyssinian",
"Abyssinian-Guinea-Pig",
"Acadian-Flycatcher",
"Achrioptera-Manga",
"Ackie-Monitor",
"Addax",
"Adelie-Penguin",
"Aesculapian-snake",
"Affenpinscher",
"Afghan-Hound",
"African-Bullfrog",
"African-Bush-Elephant",
"African-Civet",
"African-Clawed-Frog",
"African-Fish-Eagle",
"African-Forest-Elephant",
"African-Golden-Cat",
"African-Grey-Parrot",
"African-Jacana",
"African-Palm-Civet",
"African-Penguin",
"African-Tree-Toad",
"African-Wild-Dog",
"Africanized-bee-killer-bee",
"Agama-Lizard",
"Aidi",
"Ainu",
"Airedale-Terrier",
"Airedoodle",
"Akbash",
"Akita",
"Akita-Shepherd",
"Alabai-Central-Asian-Shepherd",
"Alaskan-Husky",
"Alaskan-Klee-Kai",
"Alaskan-Malamute",
"Alaskan-Pollock",
"Alaskan-Shepherd",
"Albacore-Tuna",
"Albatross",
"Albertonectes",
"Albino-Amelanistic-Corn-Snake",
"Aldabra-Giant-Tortoise",
"Alligator-Gar",
"Allosaurus",
"Allosaurus",
"Alpaca",
"Alpine-Dachsbracke",
"Alpine-Goat",
"Alusky",
"Amano-Shrimp",
"Amargasaurus",
"Amazon-Parrot",
"Amazon-River-Dolphin-Pink-Dolphin",
"Amazon-Tree-Boa",
"Amazonian-Royal-Flycatcher",
"Ambrosia-Beetle",
"American-Alligator",
"American-Alsatian",
"American-Bulldog",
"American-Bully",
"American-Cocker-Spaniel",
"American-Cockroach",
"American-Coonhound",
"American-Dog-Tick",
"American-Eel",
"American-Eskimo-Dog",
"American-Foxhound",
"American-Hairless-Terrier",
"American-Leopard-Hound",
"American-Paddlefish",
"American-Pit-Bull-Terrier",
"American-Pugabull",
"American-Pygmy-Goat",
"American-Robin",
"American-Staffordshire-Terrier",
"American-Toad",
"American-Water-Spaniel",
"American-Wirehair",
"Amethystine-Python-Scrub-Python",
"Amphicoelias-Fragillimus",
"Amur-Leopard",
"Anaconda",
"Anatolian-Shepherd-Dog",
"Anchovies",
"Andrewsarchus",

            "Rose","Chrysanthemum","Daisy","Jasmine","Gerbera","CarnationPoppy","Tulip","Lily","Lotus","Hibiscus","PeonySunflower","Lilac","Aster","Dandelion","Marigold","DahliaLamium-Dead-nettles","Datura-Devil-strumpet","Cosmos","Broom","ColumbineCassiafistula-Goldenshower","Waterlily","Pansy","Plumeria","Adeniumobesum-Desertrose","AmaranthusViola","Allamandacathartica-Goldentrumpet-Commontrumpetvine","Hederahelix-Commonivy","Falseindigo-Amorphafruticosa","Feverfew","DwarfmorninggloryMogra-Arabianjasmine","Bird-of-paradiseflower","Palash-Flameoftheforest","Phoenixflower-Royalpoinciana-Gulmoharflower","Cherryblossom-Sakura","Geraniumflower-Crane-s-billPurplegianthyssop","Orchid","Fiestadaisy","Anthurium-Laceleaf","Goldenrod-Solidago","DaffodilsGladiolus-Swordlily","Featherflower","FalseSalvia","Elderberry","Eveningprimrose","FoxgloveFairycandles","Elephantearflower","Moonflower-Nightglory","Whitebaneberry-Doll-seyes","Forget-me-notflower","Pipevine-Dutchman-sPipeflower"          
                         
                        ];
        
        
         
        
        let randlen= randomWord.length;
       
        let elementIndex = Math.floor(Math.random()*randlen);
        let resultWord = randomWord[elementIndex];
          
          
        
        
        
        
        let math1=Math.random()*10;
        favNameValue = favNameValue + parseInt(math1)  ;
        
        favNameValue = favNameValue + resultWord;
         
        result = favNameValue+'@'+providerAreaValue+'.com';
        
        
      }
      
        
      
      
            document.getElementById('resultBox').style.fontSize='20px';
      document.getElementById('resultBox').style.display='';
    document.getElementById('resultBox').innerText=  document.getElementById('resultBox').innerText.replaceAll('%UD%',result);

  
      document.getElementById('buttonGen').style.display='none';
      
      document.getElementById('warnArea').innerText="*Refresh the page to generate another one.";
    
      document.getElementById('warnArea').style.color="red";
      
      document.getElementById('warnArea').style.textShadow="";
      
      document.getElementById('warnArea').style.fontSize="18px";
      

   
}