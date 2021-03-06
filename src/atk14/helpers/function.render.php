<?php
/**
* {render partial="search_box"}
*	toto povede k natazeni sablonky _search_box.tpl
*
* {render partial="shared/user_info"}
* toto zase povede k natazeni sablonky shared/_user_info.tpl
*
*
* Render parial se pouziva i misto {foreach}
*
*	{render parial="product" from=$products item=product} 
*
* {render parial="article" from=$articles item=article key=article_id}
* 
* {render parial=article_item from=$articles item=article}
* {render parial=article_item from=$articles} {* zde bude item automaticky nastaveno na article *}
*
*
* Dale je mozne render pouzit misto for(;;){ }:
* {render parial="list_item" for=1 to=10 step=1 item=i}
*/
function smarty_function_render($params,$template){
	$smarty = atk14_get_smarty_from_template($template);

	// $solve_smarty_vs_template_problem is true when Smarty3 is being used
	$solve_smarty_vs_template_problem = get_class($smarty)!=get_class($template);

	Atk14Timer::Start("helper function.render");
	$template_name = $partial = $params["partial"];
	unset($params["partial"]);

	$template_name = preg_replace("/([^\\/]+)$/","_\\1",$template_name);
	$template_name .= ".tpl";

	if(in_array("from",array_keys($params)) && (!isset($params["from"]) || sizeof($params["from"])==0)){ return ""; }

	$original_smarty_vars = $smarty->getTemplateVars();
	if($solve_smarty_vs_template_problem){
		// in Smarty3 $smarty doesn't know variables assigned in a $template
		//
		//		{* template index.tpl *}
		//		{assign var="flower" value="rose"}
		//		{render prtial="partial"}
		//
		//		{* template _partial.tpl *}
		//		{$flower} {* normally $smarty doesn't know about a rose *}
		//
		// in order to avoid this behavior we have to assign all $template`s vars to $smarty
		$original_tpl_vars = $template->getTemplateVars();
		$smarty->assign($original_tpl_vars);
	}

	$out = array();


	if(!isset($params["from"])){
	
		foreach($params as $key => $value){	
			$smarty->assign($key,$value);
		}

		$out[] = $smarty->fetch($template_name);

	}else{

		$key = null;
		
		if(!is_numeric($params["from"])){
			$collection = $params["from"];
			$key = isset($params["key"]) ? $params["key"] : null;
			unset($params["key"]);
		}else{
			$collection = array();
			$to = isset($params["to"]) ? (int)$params["to"] : 0;
			// TODO: poresit zaporny stepping
			$step = isset($params["step"]) ? (int)$params["step"] : 1;
			$step==0 && ($step = 1);
			
			for($i=(int)$params["from"];$i<=$to;$i += $step){
				$collection[] = $i;
			}
			unset($params["to"]);
			unset($params["step"]);
		}

		$item = null;
		if(isset($params["item"])){
			$item = $params["item"];
		}elseif(preg_match("/([^\\/]+)$/",$partial,$matches)){
			$item = $matches[1];
		}

		unset($params["item"]);
		unset($params["from"]);

		$collection_size = sizeof($collection);
		$counter = 0;
		foreach($collection as $_key => $_item){
			if(isset($key)){ $smarty->assign($key,$_key); }
			if(isset($item)){ $smarty->assign($item,$_item); }
			$smarty->assign("__counter__",$counter); // consider $__counter__ as obsolete, use $__index__ instead
			$smarty->assign("__index__",$counter);
			$smarty->assign("__iteration__",$counter+1);
			$smarty->assign("__first__",$counter==0);
			$smarty->assign("__last__",$counter==($collection_size-1));
			$smarty->assign("__total__",$collection_size);

			// zbytek parametru se naasignuje do sablonky jako v predhozim pripade
			foreach($params as $key => $value){
				$smarty->assign($key,$value);
			}

			$out[] = $smarty->fetch($template_name);
			$counter++;
		}
	}

	// vraceni puvodnich hodnot do smarty objectu
	$smarty->clearAllAssign();
	$smarty->assign($original_smarty_vars);

	if($solve_smarty_vs_template_problem){
		$template->clearAllAssign();
		$template->assign($original_tpl_vars);
	}

	Atk14Timer::Stop("helper function.render");

	return join("",$out);
}
