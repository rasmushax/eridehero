<?php

$website = array(
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

?>