var pageCount = 1;
if(document.querySelector("#reviews").dataset.itemcount){
var pageCount = 6;
} else {
var pageCount = 999;
}

var options = {
	valueNames: [ { data: ['name']}, { data: ['speed']},{ data: ['range']},{ data: ['brand']},{ data: ['price']}],
	page: pageCount,
	pagination: true
};

var userList = new List('reviews', options);

userList.on('updated', function(){
	if(userList.visibleItems.length > 0){
		document.getElementById("reviewsempty").style.display = "none";
		document.getElementById("loadreviews").style.display = "block";
	} else {
		document.getElementById("reviewsempty").style.display = "block";
		document.getElementById("loadreviews").style.display = "none";
	}
});

loadReviews = function() {
    userList.show(0, pageCount + 12);
	pageCount = pageCount + 12;
	console.log(userList.size());
	console.log(pageCount);
	if(userList.size() <= pageCount){
		document.getElementById("loadreviews").remove();
	}
    return false;
}

var currentBrand = "all";
var currentPrices = "all";

jQuery(document).ready(function($) {
	
	$(".reviews-sortby-text-container").click(function(){
		$(this).parent().find(".reviews-sortby-modal").slideToggle(200);
	});
	
	//new sortby brand is clicked
	$(".reviews-sortbybrand .reviews-sortby-modal li").click(function(){
		
		$(".reviews-sortbybrand .reviews-sortby-modal li").removeClass("current");
		$(this).addClass("current");
		$(".reviews-sortbybrand .reviews-sortby-text").text($(this).find("span").text());
		$(".reviews-sortbybrand .reviews-sortby-modal").slideToggle(200);
		
		currentBrand = $(this).data("brand");
		
		updateList();
		
	});
	
	//new sortby price is clicked
	$(".reviews-sortbyprice .reviews-sortby-modal li").click(function(){
		
		$(".reviews-sortbyprice .reviews-sortby-modal li").removeClass("current");
		$(this).addClass("current");
		$(".reviews-sortbyprice .reviews-sortby-text").text($(this).find("span").text());
		$(".reviews-sortbyprice .reviews-sortby-modal").slideToggle(200);
		
		currentPrices = $(this).data("prices");
		
		updateList();
		
	});
	
	
	function updateList(){

		userList.filter(function (item) {
			
			var returning = true;
			
			if(currentBrand !== "all"){
				if(currentBrand !== item.values().brand){
					returning = false;
				}
			}
			
			if(currentPrices !== "all"){
				var currentPrice = currentPrices.split("-");
				if(!item.values().price || item.values().price === "null" || item.values().price == 0){
					returning = false;
				}
				
				if(parseInt(item.values().price) < parseInt(currentPrice[0])){
					returning = false;
				}
				
				if(parseInt(item.values().price) > parseInt(currentPrice[1])){
					returning = false;
				}
			}
			
			return returning;
			
		});
		
	}
	
	
	
})