var options = {
    valueNames: [ 'pc-name', { data: ['instock']}, { data: ['deal']}, { data: ['price']}, { data: ['brand']}, { data: ['discount']} ],
    page: 20,
    pagination: true,
    searchDelay: 500
};

var userList = new List('pricecomparison', options);

userList.sort("pc-name", {
    order: "asc"
});

jQuery(document).ready(function($) {
    
    $(".pc-filterupdate").click(updateList);
    
    function updateList(){
        var instock = $(".checkbox-instock").is(":checked");
        var deal = $(".checkbox-deals").is(":checked");
        var minprice = $("input.minprice");
        var maxprice = $("input.maxprice");
        var brands = $("input.pc-brand-checkbox:checked");
        const brandarr = new Array();
        
        brands.each(function() {
            brandarr.push($(this).data("brand"));
        });

        userList.filter(function (item) {
            var returning = true;
            
            if(instock == true){
                if(item.values().instock != 1){
                    returning = false;
                }
            }
            
            if(deal == true){
                if(item.values().deal == 0){
                    returning = false;
                }
            }
            
            if(minprice.val()) {
                if(parseInt(item.values().price) < minprice.val()){
                    returning = false;
                }
            }
            
            if(maxprice.val()) {
                if(parseInt(item.values().price) > maxprice.val()){
                    returning = false;
                }
            }
            
            if(brands.length){
                if(!brandarr.includes(item.values().brand)){
                    returning = false;
                }
            }
            
            return returning;
        });
        
        $(".pc-filterupdate").addClass("disabled").prop("disabled", true);
        if($(".pc-settings").hasClass("openedmobile")){
            $(".pc-settings").fadeToggle();
            $("body").toggleClass("stopscroll");
        }
    }
    
    userList.on("updated", function() {
        $(".pc-filtersnum").html(" (" + userList.matchingItems.length + "/" + userList.items.length + ")");
    });
  
    $('.checkbox input').change(function(){
        $(this).siblings("div").toggleClass("checked");
    });
    
    $('input.pc-brand-checkbox').change(function(){
        $(this).parent().toggleClass('activated');
    });
    
    $('.pc-settings input').change(function(){
        $(".pc-filterupdate").removeClass("disabled").prop("disabled", false);
    });
    
    // resets all filters when button is clicked
    $('button.pc-filterreset').click(function(){
        
        // clears search field and runs an empty search
        $("input.search").val("");
        userList.search();
        
        // resetting all filters in the HTML dom
        $('input.pc-brand-checkbox').prop('checked', false);
        $(".pc-brand").removeClass("activated");
        $(".pc-pricerange input").val("");
        $('.checkbox input').prop('checked', false);
        $(".checkbox div").removeClass("checked");
        $(".pc-filterupdate").addClass("disabled");
        $(".pc-filterupdate").prop('disabled', false);
        
        // filtering the list with no filters enabled        
        userList.filter();        
    });
    
    // sort by functionality
    // open dropdown on click
    $(".pc-sortby-text-container").click(function(){
        $(".pc-sortby-modal").slideToggle(200);
    });
    
    //new sortby is clicked
    $(".pc-sortby-modal li").click(function(){
        
        if($(this).data("sortby") == "name"){
            userList.sort("pc-name", {
                order: "asc"
            });
        }
        
        if($(this).data("sortby") == "price_asc"){
            userList.sort("price", {
                order: "asc"
            });
        }
        
        if($(this).data("sortby") == "price_desc"){
            userList.sort("price", {
                order: "desc"
            });
        }
        
        if($(this).data("sortby") == "discount"){
            userList.sort("discount", {
                order: "desc"
            });
        }
        
        $(".pc-sortby-modal li").removeClass("current");
        $(this).addClass("current");
        $(".pc-sortby-text b").text($(this).find("span").text());
        $(".pc-sortby-modal").slideToggle(200);
    });
    
    /** EXPAND BRANDS **/
    $(".pc-brands-expand").click(function(e){
        e.preventDefault();
        $(".pc-brands").toggleClass("opened");
        var newtext = $(this).find("span").data("text");
        $(this).find("span").data("text", $(this).find("span").html());
        $(this).find("span").html(newtext);
    });
    
    /** MODAL STUFF **/
    $(document).on("click", ".pc-modal-close", function(){
        $(".pc-modal").fadeOut(200);
    });

    $(document).on("click", ".pc-item-container", function(){
        var itemclicked = $(this);
        
        if($(this).hasClass("expanded")){
            itemclicked.removeClass("expanded");
            itemclicked.next().slideUp(200);
        } else {
            if(itemclicked.next().find(".pc-item-subcontainer-content").html() == ""){
                
                itemclicked.find(".pc-item-chevron").html('<span class="loader"></span>');
                
                // Use WordPress AJAX instead of direct file access
                $.ajax({
                    url: product_offers_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_product_offers',
                        id: $(this).parent().data("itemid"),
                        nonce: product_offers_ajax.nonce
                    },
                    cache: false,
                    context: document.body
                }).done(function(response) {
                    if (response.success) {
                        itemclicked.addClass("expanded");
                        itemclicked.next().delay(100).slideDown(200);
                        itemclicked.next().find(".pc-item-subcontainer-content").html(response.data);
                    } else {
                        itemclicked.addClass("expanded");
                        itemclicked.next().delay(100).slideDown(200);
                        itemclicked.next().find(".pc-item-subcontainer-content").html("<p style='padding: 1em;'>Error: " + response.data + "</p>");
                    }
                }).fail(function() {
                    itemclicked.addClass("expanded");
                    itemclicked.next().delay(100).slideDown(200);
                    itemclicked.next().find(".pc-item-subcontainer-content").html("<p style='padding: 1em;'>An error occurred.</p>");
                }).always(function(){
                    itemclicked.find(".pc-item-chevron").delay(100).queue(function(n) {
                        $(this).html('<svg aria-hidden="true" width="30" height="30" preserveAspectRatio="none" viewBox="0 0 24 24"><use href="#Chevron"></use></svg>');
                        n();
                    });
                });

            } else {
                itemclicked.addClass("expanded");
                itemclicked.next().slideDown(200);
            }
        }
    });
    
    // mobile filters
    $(".pc-filtersmobile, .pc-filtersclose").click(function(){
        $(".pc-settings").addClass("openedmobile").fadeToggle({
            start: function () {
                $(this).css({
                    display: "flex"
                });
            }
        });
        $("body").toggleClass("stopscroll");
    });
    
    updateList();
});