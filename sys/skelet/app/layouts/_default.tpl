<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$lang}" lang="{$lang}">
	<head>
		<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
		<meta http-equiv="content-language" content="{$lang}" />

		{render partial=shared/layout/meta_headers}

		<title>{$page_title|h} | Snake Oil</title>
		<meta name="description" content="{$page_description|h}" />

		{stylesheet_link_tag file="reset.css" media="screen, projection, print"}
		{stylesheet_link_tag file="styles.css" media="screen, projection, print"}

		<script type="text/javascript" src="http{if $request->ssl()}s{/if}://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>
		<script type="text/javascript" src="http{if $request->ssl()}s{/if}://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/jquery-ui.min.js"></script>
		{javascript_script_tag file="atk14.js"}

		{placeholder for="head"}

		{javascript_tag}
			{placeholder for="js"}
			$(function() \{
				{placeholder for="domready"}
			\});
		{/javascript_tag}	
	</head>
	<body id="body_{$controller}_{$action}">

		{render partial=shared/layout/flash_message}

		{placeholder}

	</body>
</html>