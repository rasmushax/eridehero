let elementsArray = document.querySelectorAll(".acfvideo");

elementsArray.forEach(function(elem) {
    
	elem.addEventListener("click", function(e) {
        vidClicked(e);
    });
	
	elem.querySelector("video").addEventListener("canplaythrough", (event) => {
	  event.target.closest(".acfvideo").querySelector(".vid_loading").style.display = "none";
	  event.target.closest(".acfvideo").querySelector("img").style.display = "none";
	  event.target.play();
	  event.target.style.display = "block";
	});
	
});

function vidClicked(e){
	var con = e.target.closest(".acfvideo");
	if(!con.querySelector("video").getAttribute('src')){
		con.querySelector("video").setAttribute('src',con.getAttribute('data-src'));
	}
	if (con.querySelector("img").style.display !== "none"){
		con.querySelector(".vid_play").style.display = "none";
		con.querySelector(".vid_loading").style.display = "block";
		con.querySelector("video").load();
	} else {
		if(con.querySelector("video").paused == false){
			con.querySelector("video").pause();
			con.querySelector(".vid_play").style.display = "block";
		} else {
			con.querySelector("video").play();
			con.querySelector(".vid_play").style.display = "none";
		}
	}
}