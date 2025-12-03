<?php

function prettydomain($domain){
	
	$list = array(
		"shop.niu.com" => "NIU",
		"shopeu.niu.com" => "NIU EU",
		"niu.com" => "NIU",
		"store.segway.com" => "Segway",
		"fluidfreeride.com" => "FluidFreeRide",
		"turboant.com" => "TurboAnt",
		"varlascooter.com" => "Varla",
		"apolloscooters.co" => "Apollo Scooters",
		"sisigad.com" => "SISIGAD",
		"gyroorboard.com" => "Gyroor",
		"vmax-escooter.us" => "VMAX",
		"kugooscooterusa.com" => "Kugoo USA",
		"kaabousa.com" => "Kaabo USA",
		"naveetech.us" => "Navee USA",
		"shop.e-twow.com" => "E-TWOW USA",
		"aventon.com" => "Aventon",
		"ride1up.com" => "Ride1Up",
		"radpowerbikes.com" => "Rad Power Bikes",
		"velowavebikes.com" => "Velowave",
		"murfelectricbikes.com" => "MURF",
		"shop.niu.com" => "NIU",
		"heybike.com" => "Heybike",
		"lectricebikes.com" => "Lectric",
		"giant-bicycles.com" => "Giant",
		"angrycatfishbicycle.com" => "Angry Catfish",
		"mokwheel.com" => "Mokwheel",
		"e-twow.com" => "E-TWOW",
		"vvolt.com" => "Vvolt",
		"scott-sports.com" => "Scott",
		"jensonusa.com" => "Jenson USA",
		"evo.com" => "EVO",
		"rossignol.com" => "Rossignol",
		"speedwayridersnyc.com" => "Speedway Riders",
		"jasionbike.com" => "JasionBike",
	);
	
	if(array_key_exists($domain, $list)){
		return $list[$domain];
	} else {
		return ucfirst(explode(".",$domain)[0]);
	}

}

  // Function to extract domain from URL and remove "www." if present
function extractDomain($url) {
    $parsedUrl = parse_url($url);
    $domain = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';

    // Handle ShareASale links
    if ($domain === 'shareasale.com') {
        parse_str($parsedUrl['query'] ?? '', $query);
        if (isset($query['urllink'])) {
            $decodedUrl = urldecode($query['urllink']);
            $targetParsedUrl = parse_url($decodedUrl);
            if (isset($targetParsedUrl['host'])) {
                $domain = $targetParsedUrl['host'];
            }
        }
    }
	
	// Handle Avantlink links
    if ($domain === 'www.avantlink.com') {
        parse_str($parsedUrl['query'] ?? '', $query);
        if (isset($query['url'])) {
            $decodedUrl = urldecode($query['url']);
            $targetParsedUrl = parse_url($decodedUrl);
            if (isset($targetParsedUrl['host'])) {
                $domain = $targetParsedUrl['host'];
            }
        }
    }
	
	// Handle CJ links
    if ($domain === 'www.tkqlhce.com') {
        parse_str($parsedUrl['query'] ?? '', $query);
        if (isset($query['url'])) {
            $decodedUrl = urldecode($query['url']);
            $targetParsedUrl = parse_url($decodedUrl);
            if (isset($targetParsedUrl['host'])) {
                $domain = $targetParsedUrl['host'];
            }
        }
    }
	
	// Handle Awin links
    if ($domain === 'www.awin1.com') {
        parse_str($parsedUrl['query'] ?? '', $query);
        if (isset($query['ued'])) {
            $decodedUrl = urldecode($query['ued']);
            $targetParsedUrl = parse_url($decodedUrl);
            if (isset($targetParsedUrl['host'])) {
                $domain = $targetParsedUrl['host'];
            }
        }
    }
	
	// Handle Partnerboost links
    if ($domain === 'app.partnerboost.com') {
        parse_str($parsedUrl['query'] ?? '', $query);
        if (isset($query['url'])) {
            $decodedUrl = urldecode($query['url']);
            $targetParsedUrl = parse_url($decodedUrl);
            if (isset($targetParsedUrl['host'])) {
                $domain = $targetParsedUrl['host'];
            }
        }
    }
	
	// Impact deeklinks
	if (strpos($domain, 'pxf.io') !== false) {
		parse_str($parsedUrl['query'] ?? '', $query);
		if (isset($query['u'])) {
			$decodedUrl = urldecode($query['u']);
			$targetParsedUrl = parse_url($decodedUrl);
			if (isset($targetParsedUrl['host'])) {
				$domain = $targetParsedUrl['host'];
			}
		}
	}
	
	// Handle Partnerboost links
    if ($domain === 'go.sjv.io') {
        parse_str($parsedUrl['query'] ?? '', $query);
        if (isset($query['u'])) {
            $decodedUrl = urldecode($query['u']);
            $targetParsedUrl = parse_url($decodedUrl);
            if (isset($targetParsedUrl['host'])) {
                $domain = $targetParsedUrl['host'];
            }
        }
    }
	
	    // Handle Walmart special case
    if ($domain === 'goto.walmart.com') {
        return 'walmart.com';
    }
	
	if ($domain === 'store.inmotionworld.com') {
        return 'inmotionworld.com';
    }
	
	if ($domain === 'shop.niu.com') { return 'niu.com'; }
	if ($domain === 'store.segway.com') { return 'segway.com'; }

    // Remove 'www.' prefix
    $domain = preg_replace('/^www\./', '', $domain);

    return $domain ?: 'shareasale.com'; // Fallback to shareasale.com if domain is empty
}





function getShopImg($domain,$class = ""){
	
	$domains = array(
		"niu.com" => "https://eridehero.com/wp-content/uploads/2024/06/niu-logo.png",
		"apolloscooters.co" => "https://eridehero.com/wp-content/uploads/2024/06/Apollo-logo.png",
		"fluidfreeride.com" => "https://eridehero.com/wp-content/uploads/2024/06/Fluidfreeride-logo.png",
		"atomiscooters.com" => "https://eridehero.com/wp-content/uploads/2024/06/Atomi-logo.png",
		"hiboy.com" => "https://eridehero.com/wp-content/uploads/2024/06/Hiboy-logo.png",
		"gotrax.com" => "https://eridehero.com/wp-content/uploads/2024/06/Gotrax-Logo.png",
		"punkelectric.com" => "https://eridehero.com/wp-content/uploads/2024/06/Punk-Electric-Logo.png",
		"segway.com" => "https://eridehero.com/wp-content/uploads/2024/06/Segway-logo.png",
		"revrides.com" => "https://eridehero.com/wp-content/uploads/2024/06/RevRides-logo.png",
		"splach.bike" => "https://eridehero.com/wp-content/uploads/2024/06/Splach-logo.png",
		"turboant.com" => "https://eridehero.com/wp-content/uploads/2024/06/Turboant-Logo.png",
		"uscooters.com" => "https://eridehero.com/wp-content/uploads/2024/06/Uscooters-logo.png",
		"amazon.com" => "https://eridehero.com/wp-content/uploads/2024/06/Amazon-logo.png",
		"voromotors.com" => "https://eridehero.com/wp-content/uploads/2024/06/Voro-Motors-logo.png",
		"minimotorsusa.com" => "https://eridehero.com/wp-content/uploads/2024/06/Minimotors-USA-logo.png",
		"walmart.com" => "https://eridehero.com/wp-content/uploads/2024/06/Walmart-logo.png",
		"anyhill.com" => "https://eridehero.com/wp-content/uploads/2024/07/Anyhill.png",
		"meepoboard.com" => "https://eridehero.com/wp-content/uploads/2024/07/Meepo-Logo.png",
		"maxfind.com" => "https://eridehero.com/wp-content/uploads/2024/07/Maxfind-logo.png",
		"backfireboards.com" => "https://eridehero.com/wp-content/uploads/2024/07/Backfire.png",
		"eovanboard.com" => "https://eridehero.com/wp-content/uploads/2024/07/Eovan.png",
		"haloboard.com" => "https://eridehero.com/wp-content/uploads/2024/07/Haloboard-logo.png",
		"megawheels.com" => "https://eridehero.com/wp-content/uploads/2024/07/Megawheels.png",
		"onsra.com" => "https://eridehero.com/wp-content/uploads/2024/07/Onsra-logo.png",
		"ownboard.net" => "https://eridehero.com/wp-content/uploads/2024/07/Ownboard.png",
		"possway.com" => "https://eridehero.com/wp-content/uploads/2024/07/Possway-logo.png",
		"ridepropel.com" => "https://eridehero.com/wp-content/uploads/2024/07/Propel-logo.png",
		"metro-board.com" => "https://eridehero.com/wp-content/uploads/2024/07/Metroboard-logo.png",
		"bajaboard.com.au" => "https://eridehero.com/wp-content/uploads/2024/07/Bajaboard.png",
		"vmax-escooter.us" => "https://eridehero.com/wp-content/uploads/2024/07/Vmax.png",
		"inmotionworld.com" => "https://eridehero.com/wp-content/uploads/2024/08/Inmotion-logo.png",
		"gotrax.com" => "https://eridehero.com/wp-content/uploads/2024/08/Gotrax.png",
		"kugooscooterusa.com" => "https://eridehero.com/wp-content/uploads/2024/08/Kugoo.png",
		"inokim.com" => "https://eridehero.com/wp-content/uploads/2024/08/Inokim.png",
		"roadrunnerscooters.com" => "https://eridehero.com/wp-content/uploads/2024/08/Roadrunner-scooters.png",
		"yumescooter.com" => "https://eridehero.com/wp-content/uploads/2024/08/Yume.png",
		"us.dyucycle.com" => "https://eridehero.com/wp-content/uploads/2024/09/DYU.png",
		"haoqiebike.com" => "https://eridehero.com/wp-content/uploads/2024/09/Haoqi.png",
		"naveetech.us" => "https://eridehero.com/wp-content/uploads/2025/08/Navee.png",
		"kaabousa.com" => "https://eridehero.com/wp-content/uploads/2025/08/Kaabo-USA.png",
		"ausomstore.com" => "https://eridehero.com/wp-content/uploads/2025/08/Ausom.png",
		"circooter.com" => "https://eridehero.com/wp-content/uploads/2025/08/Circooter.png",
		"shop.e-twow.com" => "https://eridehero.com/wp-content/uploads/2025/08/etwow.png",
		"teewing.com" => "https://eridehero.com/wp-content/uploads/2025/08/efc0f544-4c56-44b6-870f-bcf6a96402681-e1756308731733.jpg",
		"aventon.com" => "https://eridehero.com/wp-content/uploads/2025/09/aventon.png",
		"ride1up.com" => "https://eridehero.com/wp-content/uploads/2025/09/Ride1Up.png",
		"go.ride1up.com" => "https://eridehero.com/wp-content/uploads/2025/09/Ride1Up.png",
		"radpowerbikes.com" => "https://eridehero.com/wp-content/uploads/2025/09/rad-power-bikes.png",
		"velowavebikes.com" => "https://eridehero.com/wp-content/uploads/2025/09/Velowave.png",
		"murfelectricbikes.com" => "https://eridehero.com/wp-content/uploads/2025/09/murf.png",
		"shop.niu.com" => "https://eridehero.com/wp-content/uploads/2025/09/Niu_Technologies_Logo.png",
		"shopeu.niu.com" => "https://eridehero.com/wp-content/uploads/2025/09/Niu_Technologies_Logo.png",
		"shopca.niu.com" => "https://eridehero.com/wp-content/uploads/2025/09/Niu_Technologies_Logo.png",
		"niu.com" => "https://eridehero.com/wp-content/uploads/2025/09/Niu_Technologies_Logo.png",
		"heybike.com" => "https://eridehero.com/wp-content/uploads/2025/09/heybike.png",
		"lectricebikes.com" => "https://eridehero.com/wp-content/uploads/2025/09/lectric.png",
		"giant-bicycles.com" => "https://eridehero.com/wp-content/uploads/2025/09/Giant-Logo-Blue1.jpg",
		"angrycatfishbicycle.com" => "https://eridehero.com/wp-content/uploads/2025/09/acb-logo-full1.svg",
		"mokwheel.com" => "https://eridehero.com/wp-content/uploads/2025/09/mokwheel.png",
		"e-twow.com" => "https://eridehero.com/wp-content/uploads/2025/09/5941752459481_.pic1_.png",
		"vvolt.com" => "https://eridehero.com/wp-content/uploads/2025/09/2024091222-16cc058f-51d9-41c1-8dd5-0c521f1ddab7-untitled1.jpg",
		"scott-sports.com" => "https://eridehero.com/wp-content/uploads/2025/09/scott.png",
		"jensonusa.com" => "https://eridehero.com/wp-content/uploads/2025/09/jensonusa-logo-horizontal-870x1591-1.jpg",
		"evo.com" => "https://eridehero.com/wp-content/uploads/2025/09/evo.png",
		"rossignol.com" => "https://eridehero.com/wp-content/uploads/2025/09/rossignol.png",
		"jasionbike.com" => "https://eridehero.com/wp-content/uploads/2025/10/jasionbike.png",
	);
	
	if(isset($domains[$domain])){
		return "<img loading='lazy' decoding='async' src='".$domains[$domain]."' alt='".$domain."' class='".$class."' />";
	} else {
		return;
	}
}

?>