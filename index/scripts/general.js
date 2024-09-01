document.addEventListener('DOMContentLoaded', function() {
    // Apply random filters to an element with id 'yunus-pop-art'
    const yunus_pop_art = document.querySelector('#yunus-pop-art');
    if (yunus_pop_art) {
        const randomRotation = () => Math.floor(Math.random() * 6);
        const stableSet = '247';
        if (Math.random() * 2 > 1) {
            yunus_pop_art.style.filter = `grayscale(42%) sepia(0%) hue-rotate(0deg)`;
        } else {
            yunus_pop_art.style.filter = `grayscale(42%) sepia(0%) hue-rotate(0deg)`;
        }
    }

    // Perform image animation by adding/removing cloned images
    const img_animation_div = document.querySelector('#img-animation-div');
    if (img_animation_div) {
        const imgItem = img_animation_div.querySelector('img');
        setInterval(() => {
            if (img_animation_div.childNodes.length > 2) {
                img_animation_div.removeChild(img_animation_div.childNodes[0]);  
                setTimeout(() => {
                    img_animation_div.removeChild(img_animation_div.childNodes[1]);
                }, 125);
            } else {
                const cloned = imgItem.cloneNode();
                cloned.removeAttribute('id');
                cloned.style.filter = `grayscale(73%) sepia(44%) hue-rotate(143deg)`;
                cloned.style.opacity = '0.2';
                img_animation_div.appendChild(cloned);
            }
            img_animation_div.querySelector('img').style.filter = `grayscale(0%) sepia(0%)`;
            img_animation_div.querySelector('img').style.opacity = '1';
        }, 350);
    }

     const h1 = document.querySelector('h1');
    if (h1 && h1.innerText.includes("Welcome to Yunus Emre Vurgun's Website")) {
         let interval = setTimeout(() => {
            h1.innerHTML += " <span style=\"color:#22252A;font-size:0.45em\"><i>{Software Developer}</i></span>" ;     }, 1500);
     }
});
