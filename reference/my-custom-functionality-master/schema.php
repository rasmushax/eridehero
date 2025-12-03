<?php

// Load all schema files

function addSchema(){
	
	/** INITIAL SETUP AND GRAPH **/
	$schema = array(
		"@context" => "https://schema.org",
		"@graph" => []
	);
	$permalink = get_permalink();
	$getpost = get_post();
	
	/** ORGANISATION **/
	$schema["@graph"][] = array(
		"@type" => "Organization",
		"@id" => "https://eridehero.com/#organization",
		"url" => "https://eridehero.com",
		"sameAs" => array(
			"https://www.youtube.com/c/eRideHero",
			"https://www.facebook.com/eridehero/",
			"https://www.instagram.com/eridehero/",
			"https://www.linkedin.com/company/eridehero",
			"https://twitter.com/eRideHero",
			"https://muckrack.com/media-outlet/eridehero",
			"https://www.crunchbase.com/organization/eridehero",
			"https://linktr.ee/eridehero"
		),
		"name" => "ERideHero",
		"description" => "ERideHero is a consumer-centric, data-driven guide to personal electric vehicles and micromobility.",
		"address" => array(
			"@type" => "PostalAddress",
			"streetAddress" => "Boulevarden 26A, 2",
			"addressLocality" => "Aalborg",
			"addressRegion" => "North Jutland",
			"addressCountry" => "DK",
			"postalCode" => "9000"
		),
		"contactPoint" => array(
			"@type" => "ContactPoint",
			"email" => "contact@eridehero.com",
			"telephone" => "+4542555011"
		),
		"email" => "contact@eridehero.com",
		"numberOfEmployees" => array(
			"@type" => "QuantitativeValue",
			"value" => 3
		),
		"foundingDate" => "2019-04-04T00:00:00.000Z",
		"vatID" => "38713140",
		"slogan" => "Your data-driven guide to micromobility",
		"logo" => array(
			"@type" => "ImageObject",
			"@id" => "https://eridehero.com/#logo",
			"url" => "https://eridehero.com/wp-content/uploads/2021/09/logo-icon-big.png",
			"contentUrl" => "https://eridehero.com/wp-content/uploads/2021/09/logo-icon-big.png",
			"caption" => "ERideHero",
			"inLanguage" => "en-US",
			"width" => "1080",
			"height" => "1080"
		),
		"founder" => array(
			"@type" => "Person",
			"name" => "Rasmus Barslund",
			"url" => "https://eridehero.com/author/rasmus-barslund/",
			"sameAs" => "https://eridehero.com/author/rasmus-barslund/"
		),
		"image" => array(
			"@id" => "https://eridehero.com/#logo"
		)
	);
	
	/** WEBSITE **/
	$schema['@graph'][] = array(
		"@type" => "WebSite",
		"@id" => "https://eridehero.com/#website",
		"url" => "https://eridehero.com",
		"name" => "ERideHero",
		"description" => "The website a micromobility and personal electric vehicle publication named ERideHero",
		"publisher" => array(
			"@id" => "https://eridehero.com/#organization"
		),
		"potentialAction" => array(
			"@type" => "SearchAction",
			"target" => array(
				"@type" => "EntryPoint",
				"urlTemplate" => "https://eridehero.com/?s={q}"
			),
			"query-input" => "required name=q"
		),
		"inLanguage" => "en-US",
		"copyrightHolder" => array(
			"@id" => "https://eridehero.com/#organization"
		)
	);
	
	
	/** IMAGE OBJECT **/
	
	if(has_post_thumbnail() && !is_category() && !is_tag()){
		$thumb = array();
		$thumb_id = get_post_thumbnail_id();
		
		if($thumb_id !== 0){

			// first grab all of the info on the image... title/description/alt/etc.
			$args = array(
				'post_type' => 'attachment',
				'include' => $thumb_id
			);
			$thumbs = get_posts( $args );
			if ( $thumbs ) {
				// now create the new array
				$thumb['title'] = $thumbs[0]->post_title;
				$thumb['description'] = $thumbs[0]->post_content;
				$thumb['caption'] = $thumbs[0]->post_excerpt;
				$thumb['alt'] = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
				$thumb['sizes'] = array(
					'full' => wp_get_attachment_image_src( $thumb_id, 'full', false )
				);
				// add the additional image sizes
				foreach ( get_intermediate_image_sizes() as $size ) {
					$thumb['sizes'][$size] = wp_get_attachment_image_src( $thumb_id, $size, false );
				}
			} // end if
			
			if(!empty($thumb['sizes']['full'][0])){
				$schema['@graph'][] = array(
					"@type" => "ImageObject",
					"inLanguage" => "en-US",
					"@id" => $permalink."#primaryimage",
					"url" => $thumb['sizes']['full'][0],
					"contentUrl" => $thumb['sizes']['full'][0],
					"width" => $thumb['sizes']['full'][1],
					"height" => $thumb['sizes']['full'][2]
				);
			}
			
		}
	}
	
	/** PERSON **/
	if(is_single() || is_author()){
		$curinfo = get_post(get_the_ID());
		$person = array(
			"@type" => "Person",
			"name" => get_the_author_meta('display_name'),
			"email" => get_the_author_meta('email'),
			"worksFor" => array(
				"@id" => "https://eridehero.com/#organization"
			),
			"@id" => get_the_author_meta('user_url')."#author"
		);
		
		$fields = get_fields('user_'.get_the_author_meta('ID'));
		
		if(!empty($fields['profile_description'])) { $person['description'] = $fields['profile_description']; }
		if(!empty($fields['job_title'])) { $person['jobTitle'] = $fields['job_title']; }
		if(!empty($fields['knows_about'])) { $person['knowsAbout'] = explode(", ",$fields['knows_about']); }
		if(!empty($fields['knowsLanguage'])) { $person['knowsLanguage'] = $fields['knowsLanguage']; }
		if(!empty($fields['profile_picture']['url'])) { $person['image'] = $fields['profile_picture']['url']; }
		if(!empty($fields['additionalName'])) { $person['additionalName'] = $fields['additionalName']; }
		if(!empty($fields['birthdate'])) { $person['birthDate'] = $fields['birthdate']; }
		if(!empty($fields['gender'])) { $person['gender'] = $fields['gender']; }
		
		/** SAMEAS LINKS **/
		$social[] = get_the_author_meta("facebook");
		$social[] = get_the_author_meta("twitter");
		foreach(explode(" ",get_the_author_meta("additional_profile_urls", $author)) as $item){
			$social[] = $item;
		}
		if(count($social) > 0){
			$person['sameAs'] = $social;
		}
		
		$schema['@graph'][] = $person;
	}
	
	
	/** AUTHOR PAGE **/
	if(is_author()){
		$profilepage = array(
			"@type" => "ProfilePage",
			"name" => get_the_author_meta('display_name'),
			"url" => get_the_author_meta('user_url'),
			"mainEntity" => array(
				"@id" => get_the_author_meta('user_url')."#author"
			),
			"isPartOf" => array(
				"@id" => "https://eridehero.com/#website"
			),
			"breadcrumb" => array(
				"@id" => get_the_author_meta('user_url')."#breadcrumb"
			)
		);
		
		if(!empty($fields['profile_picture']['url'])) { $profilepage['image'] = $fields['profile_picture']['url']; }
		if(!empty($fields['profile_description'])) { $profilepage['description'] = $fields['profile_description']; }
		
		/** HasPart **/
		$posts = get_posts( array( 
				'author' => get_the_author_meta("ID"), 
				'numberposts' => 5,
				'post_type' => 'post',
				'order by' => 'modified'      
			) 
		);

		$haspart = array();

		foreach($posts as $post){
			$haspart[] = array(
				"@type" => "Article",
				"headline" => $post->post_title,
				"image" => get_the_post_thumbnail_url( $post->ID, 'full' ),
				"url" => get_permalink($post->ID),
				"datePublished" => date(DATE_ISO8601, strtotime($post->post_date)),
				"dateModified" => date(DATE_ISO8601, strtotime($post->post_modified)),
				"author" => array(
					"@id" => get_the_author_meta('user_url')."#author"
				)
			);
		}

		if(count($haspart) > 0){
			$profilepage['hasPart'] = $haspart;
		}
		
		$schema['@graph'][] = $profilepage;
		
	}
	
	/** WEBPAGE (FOR ANY POST) **/
	if(is_single()){
		$thisfields = get_fields();
		$webpage = array(
			"@type" => "WebPage",
			"@id" => $permalink."#webpage",
			"url" => $permalink,
			"name" => get_the_title(),
			"isPartOf" => array(
				"@id" => "https://eridehero.com/#website"
			),
			"author" => array(
				"@id" => get_the_author_meta('user_url')."#author"
			),
			"primaryImageOfPage" => array(
				"@id" => $permalink."#primaryimage"
			),
			"datePublished" => date(DATE_ISO8601, strtotime($curinfo->post_date)),
			"dateModified" => date(DATE_ISO8601, strtotime($curinfo->post_modified)),
			"lastReviewed" => date(DATE_ISO8601, strtotime($curinfo->post_modified)),
			"image" => get_the_post_thumbnail_url( get_the_ID(), 'full' ),
			"inLanguage" => "en-US",
			"breadcrumb" => array(
				"@id" => $permalink."#breadcrumb"
			),
			"potentialAction" => array(
				"@type" => "ReadAction",
				"target" => array($permalink)
			)
		);
		
		$postfields = get_fields();
		
		if($postfields['subtitle']){
			$webpage['description'] = $postfields['subtitle'];
		}
		
		$schema['@graph'][] = $webpage;
	}
	
	/** POST NON-REVIEW **/ 
	if(!has_tag("review") && is_single()){
		$nonreview = array(
			"@type" => "Article",
			"@id" => $permalink."#article",
			"headline" => get_the_title(),
			"url" => $permalink,
			"MainEntityOfPage" => array(
				"@id" => $permalink."#webpage"
			),
			"isPartOf" => array(
				"@id" => $permalink."#webpage"
			),
			"author" => array(
				"@id" => get_the_author_meta('user_url')."#author"
			),
			"datePublished" => date(DATE_ISO8601, strtotime($curinfo->post_date)),
			"dateModified" => date(DATE_ISO8601, strtotime($curinfo->post_modified)),
			"publisher" => array(
				"@id" => "https://eridehero.com/#organization"
			),
			"copyrightYear" => date("Y", strtotime($curinfo->post_date)),
			"inLanguage" => "en-US",
			"copyrightHolder" => array(
				"@id" => "https://eridehero.com/#organization"
			),
			"thumbnailUrl" => get_the_post_thumbnail_url( get_the_ID(), 'full' ),
			"image" => array(
				"@id" => $permalink."#primaryimage"
			),
			"isAccessibleForFree" => "true"
		);
		
		if($thisfields['subtitle']) { $review['description'] = $thisfields['subtitle']; } 
		
		$categories = get_the_category();
		$category_names = array();
		foreach ($categories as $category)
		{
			$category_names[] = $category->cat_name;
		}
		if(count($category_names) > 0){
			$nonreview['articleSection'] = implode(', ', $category_names);
		}
		$schema['@graph'][] = $nonreview;
	}
	
	/** POST REVIEW **/ 
	if(has_tag("review") && is_single()){
		$rfields = get_fields(get_field('relationship')[0]);
		
		$review = array(
			"@type" => "Product",
			"name" => $rfields['brand']." ".$rfields['model'],
			"category" => $rfields['product_type'],
			"@id" => $permalink."#product",
			"brand" => array(
				"@type" => "Brand",
				"name" => $rfields['brand']
			),
			"url" => $permalink,
			"image" => array(
				"@id" => $permalink."#primaryimage"
			),
			"description" => "The ".$rfields['brand']." ".$rfields['model']." ".$rfields['product_type'],
			"review" => array(
				"@type" => array("Review","CriticReview"),
				"@id" => $permalink."#review",
				"url" => $permalink,
				"name" => $rfields['brand']." ".$rfields['model']." ".$rfields['product_type']." Review",
				"headline" => $rfields['brand']." ".$rfields['model']." ".$rfields['product_type']." Review",
				"MainEntityOfPage" => $permalink."#webpage",
				"reviewBody" => strip_shortcodes(strip_tags(get_the_content())),
				"author" => array(
					"@id" => get_the_author_meta('user_url')."#author"
				),
				"datePublished" => date(DATE_ISO8601, strtotime($curinfo->post_date)),
				"dateModified" => date(DATE_ISO8601, strtotime($curinfo->post_modified)),
				"publisher" => array(
					"@id" => "https://eridehero.com/#organization"
				),
				"copyrightYear" => date("Y", strtotime($curinfo->post_date)),
				"inLanguage" => "en-US",
				"copyrightHolder" => array(
					"@id" => "https://eridehero.com/#organization"
				),	
				"reviewRating" => array(
					"@type" => "Rating",
					"ratingValue" => $rfields['ratings']['overall'],
					"worstRating" => 1,
					"bestRating" => 10,
					"reviewAspect" => array("Speed","Range","Motor Performance","Battery Performance","Ride Quality","Build Quality","Portability","Safety","Features","Value For Money")
				)
			)
		);
		
		if($thisfields['pros']){
			$review['review']['positiveNotes'] = array(
				"@type" => "ItemList",
				"itemListElement" => array()
			);
			
			$proscount = 1;
			foreach(preg_split("/\r\n|\n|\r/", $thisfields['pros']) as $item){
				$review['review']['positiveNotes']["itemListElement"][] = array(
					"@type" => "ListItem",
					"position" => $proscount,
					"name" => $item
				);
				$proscount++;
			}
			
		}
		
		if($thisfields['cons']){
			$review['review']['negativeNotes'] = array(
				"@type" => "ItemList",
				"itemListElement" => array()
			);
			
			$conscount = 1;
			foreach(preg_split("/\r\n|\n|\r/", $thisfields['cons']) as $item){
				$review['review']['negativeNotes']["itemListElement"][] = array(
					"@type" => "ListItem",
					"position" => $conscount,
					"name" => $item
				);
				$conscount++;
			}
			
		}
		
		$schema['@graph'][] = $review;
	}
	
	/** ABOUT PAGE **/
	if(get_field('about_page','options') == get_the_ID()){
		$curinfo = get_post(get_the_ID());
		
		$aboutpage = array(
			"@type" => "AboutPage",
			"@id" => $permalink,
			"url" => $permalink,
			"name" => "About Us - ERideHero",
			"isPartOf" => array(
				"@id" => "https://eridehero.com/#website"
			),
			"dateModified" => date(DATE_ISO8601, strtotime($curinfo->post_modified)),
			"dateCreated" => date(DATE_ISO8601, strtotime($curinfo->post_date)),
			"lastReviewed" => date(DATE_ISO8601, strtotime($curinfo->post_modified)),
			"description" => "ERideHero is the consumer-centric, data-driven guide to electric rides.",
			"inLanguage" => "en-US",
			"potentialAction" => array(
				"@type" => "ReadAction",
				"target" => array($permalink)
			),
			"breadcrumb" => array(
				"@id" => $permalink."#breadcrumb"
			)
		);
		
		if(has_post_thumbnail()){
			$aboutpage['primaryImageOfPage'] = array(
				"@id" => $permalink."#primaryimage"
			);
		}
		$schema['@graph'][] = $aboutpage;
	}
	
	/** CONTACT PAGE **/
	if(get_field('contact_page','options') == get_the_ID()){
		$curinfo = get_post(get_the_ID());
		
		$contactpage = array(
			"@type" => "ContactPage",
			"@id" => $permalink,
			"url" => $permalink,
			"name" => "Contact Us - ERideHero",
			"isPartOf" => array(
				"@id" => "https://eridehero.com/#website"
			),
			"dateModified" => date(DATE_ISO8601, strtotime($curinfo->post_modified)),
			"dateCreated" => date(DATE_ISO8601, strtotime($curinfo->post_date)),
			"lastReviewed" => date(DATE_ISO8601, strtotime($curinfo->post_modified)),
			"description" => "Get in touch with ERideHero by filling in the email form in this page or messaging us on the linked social media profiles.",
			"inLanguage" => "en-US",
			"potentialAction" => array(
				"@type" => "ReadAction",
				"target" => array($permalink)
			),
			"breadcrumb" => array(
				"@id" => $permalink."#breadcrumb"
			)
		);
		
		if(has_post_thumbnail()){
			$contactpage['primaryImageOfPage'] = array(
				"@id" => $permalink."#primaryimage"
			);
		}
		$schema['@graph'][] = $contactpage;
	}
	
	/** CATEGORY OR TAG PAGE **/
	if(is_category() || is_tag()){
		
		$contactpage = array(
			"@type" => "CollectionPage",
			"@id" => get_term_link(get_queried_object()->term_id),
			"url" => get_term_link(get_queried_object()->term_id),
			"name" => get_queried_object()->name,
			"isPartOf" => array(
				"@id" => "https://eridehero.com/#website"
			),
			"description" => get_queried_object()->description,
			"inLanguage" => "en-US",
			"potentialAction" => array(
				"@type" => "ReadAction",
				"target" => array(get_term_link(get_queried_object()->term_id))
			),
			"breadcrumb" => array(
				"@id" => get_term_link(get_queried_object()->term_id)."#breadcrumb"
			)
		);
		$schema['@graph'][] = $contactpage;
	}
	
	/** REST OF THE PAGES **/
	if($getpost->post_type == "page" && get_field('contact_page','options') !== get_the_ID() && get_field('about_page','options') !== get_the_ID()){
		$thisfields = get_fields();
		$webpage = array(
			"@type" => "WebPage",
			"@id" => $permalink."#webpage",
			"url" => $permalink,
			"name" => $getpost->post_title,
			"isPartOf" => array(
				"@id" => "https://eridehero.com/#website"
			),
			"primaryImageOfPage" => array(
				"@id" => $permalink."#primaryimage"
			),
			"datePublished" => date(DATE_ISO8601, strtotime($getpost->post_date)),
			"dateModified" => date(DATE_ISO8601, strtotime($getpost->post_modified)),
			"lastReviewed" => date(DATE_ISO8601, strtotime($getpost->post_modified)),
			"image" => get_the_post_thumbnail_url( get_the_ID(), 'full' ),
			"inLanguage" => "en-US",
			"breadcrumb" => array(
				"@id" => $permalink."#breadcrumb"
			),
			"potentialAction" => array(
				"@type" => "ReadAction",
				"target" => array($permalink)
			)
		);
		
		$postfields = get_fields();
		
		if($postfields['subtitle']){
			$webpage['description'] = $postfields['subtitle'];
		}
		
		$schema['@graph'][] = $webpage;
	}
	
	/** BREADCRUMB LIST **/
	
	$breadcrumblist = array(
		"@type" => "BreadcrumbList"
	);
	
	$push[1] = array(
		"name" => "Homepage",
		"item" => "https://eridehero.com"
	);
	
	$breadcount = 2;
	
	/** BREADCRUMB FOR POSTS **/
	if(is_single()){
		$breadcrumblist['@id'] = get_permalink($curinfo->ID)."#breadcrumb";
		$cats = get_the_category();
		usort(get_the_category(), fn($a, $b) => strcmp($a->parent, $b->parent));
		
		foreach($cats as $cat){
			$push[$breadcount] = array(
				"name" => $cat->name,
				"item" => get_term_link($cat->term_id)
			);
			$breadcount++;
		}
		
		$push[$breadcount] = array(
			"name" => $curinfo->post_title,
			"item" => get_permalink($curinfo->ID)
		);
	}
	
	/** BREADCRUMB FOR PAGES **/
	$breadcrumblist['@id'] = get_permalink($getpost->ID)."#breadcrumb";
	if($getpost->post_type == "page" && $getpost->ID !== 7){
		$push[$breadcount] = array(
			"name" => $getpost->post_title,
			"item" => get_permalink($getpost->ID)
		);
	}
	
	/** BREADCRUMB FOR CATEGORY AND TAG PAGE **/
	if(is_category() || is_tag()){
		$queried = get_queried_object();
		if($queried->parent > 0){
			$push[$breadcount] = array(
				"name" => get_term($queried->parent)->name,
				"item" => get_term_link(get_term($queried->parent)->term_id)
			);
			$breadcount++;
		}
		$push[$breadcount] = array(
			"name" => $queried->name,
			"item" => get_term_link($queried->term_id)
		);
		$breadcrumblist['@id'] = get_term_link($queried->term_id)."#breadcrumb";
	}
	
	/** BREADCRUMB FOR AUTHOR PAGE **/
	if(is_author()){
		$breadcrumblist['@id'] = get_the_author_meta('user_url')."#breadcrumb";
		$push[$breadcount] = array(
			"name" => get_the_author_meta('display_name'),
			"item" => get_the_author_meta('user_url')
		);
	}
	
	/** PUSHING THE ORDERED BREADCRUMBS **/
	$topush = array();
	foreach($push as $key => $item){
		$topush[] = array(
			"@type" => "ListItem",
			"position" => $key,
			"name" => $item['name'],
			"item" => $item['item']
		);
	}
	
	$breadcrumblist['itemListElement'] = $topush;
	
	$schema['@graph'][] = $breadcrumblist;


	$schemaoutput = json_encode( $schema, JSON_UNESCAPED_SLASHES );

	echo '<script type="application/ld+json">'.$schemaoutput.'</script>';

}

//add_action( 'wp_footer', 'addSchema' );**/

?>