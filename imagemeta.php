<?php
/*
Plugin Name: ImageMeta
Plugin URI: http://wordpress.org/plugins/imagemeta/
Description: The fastest way to manage meta-data for your wordpress images.
Version: 1.1.2
Author: era404
Author URI: http://www.era404.com
License: GPLv2 or later.
Copyright 2014 ERA404 Creative Group, Inc.
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

//globals
define('IMAGEMETA_RECORDS_PER_PAGE', 50);
add_action( 'admin_init', 'imagemeta_admin_init' );
function imagemeta_admin_init() {
	//styles
	wp_register_style( 'imagemeta_styles', plugins_url('imagemeta.css', __FILE__) );

	//scripts
	/* none */

	//ajax functions
	add_action( 'wp_ajax_ajax_action', 'ajax_updatedb' ); 	//for updates
	add_action( 'wp_ajax_purge', 'ajax_purge' ); 			//for purging/deleting unused images
	add_action( 'wp_ajax_cleanup', 'ajax_cleanup' );		//for purging/deleting unused images
}
// Setup plugin menus
add_action( 'admin_menu', 'imagemeta_admin_menu' );
function imagemeta_admin_menu() {
	$page = add_submenu_page( 	'tools.php',
								__( 'ImageMeta', 'imagemeta' ),
								__( 'ImageMeta', 'imagemeta' ),
								'administrator',
								'imagemeta',
								'imagemeta_plugin_options' );
	add_action( 'admin_print_styles-' . $page, 'imagemeta_admin_styles' );
	define('IMAGEMETA_URL', menu_page_url( 'imagemeta', false ) );
}
/***********************************************************************************
*     Add Required Styles
***********************************************************************************/
function imagemeta_admin_styles() {
	wp_enqueue_style( 'imagemeta_styles' );

	wp_enqueue_script( 'ajax-script', plugins_url('/imagemeta.js', __FILE__), array('jquery','jquery-ui-dialog'), 1.0 );
	wp_localize_script( 'ajax-script', 'ajax_object', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) ); 	// setting ajaxurl
}
/***********************************************************************************
*     Build admin page
***********************************************************************************/
function imagemeta_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	//get database object
	global $wpdb;
	
	//uploads directory
	$uploads = wp_upload_dir();
	$uploadsdir = $uploads['basedir']."/";
		
	//handle deleting images
	if(isset($_GET['cleanup']) && strstr($_GET['cleanup'],"|")){ list($cwpp,$cwppm) = explode("|",(string) trim($_GET['cleanup']));
	} else { $cwpp = 0; $cwppm = 0; }
	if(isset($_GET['remove']) && is_numeric($_GET['remove']) && $_GET['remove']>0) list($cwpp,$cwppm) = cleanup($_GET['remove']);
	
	//handle sorting
	$sorting = array("d"=>"post_date ASC",
					 "d-"=>"post_date DESC",
					 "t"=>"post_title ASC",
					 "t-"=>"post_title DESC");
	$s = $_GET['s'] = (@!isset($_GET['s']) || !array_key_exists($_GET['s'],$sorting) ? "d" : $_GET['s']);
	$sort = $sorting[$_GET['s']];
	
	//build search query, if needed
	$imgsearch = $_GET['imgsearch'] = (isset($_GET['imgsearch']) && strlen(trim($_GET['imgsearch']))>0 ? sanitize_text_field((string) trim($_GET['imgsearch'])) : false);
	$imgsearchquery = ($imgsearch ?
			"AND (  p.post_content LIKE '%{$imgsearch}%'
					OR p.post_title LIKE '%{$imgsearch}%'
					OR p.post_excerpt LIKE '%{$imgsearch}%' 
					OR REPLACE(p.guid,'{$uploadsdir}','') LIKE '%{$imgsearch}%' )"
			: "");

	//get count, first
	$cq =  "SELECT ID from {$wpdb->prefix}posts p, {$wpdb->prefix}postmeta pm
			WHERE pm.post_ID = p.ID
			AND pm.meta_key ='_wp_attached_file'
			AND p.post_mime_type IN ('image/gif','image/jpeg','image/png')
			{$imgsearchquery}
			GROUP BY p.ID";
	$crs = $wpdb->get_results($cq, ARRAY_A);
	$count = count($crs);
	
	//echo "COUNT: ".count($crs)."<br /><br />";
	/* build page array [$pg]
	*  0:total records
	*  1:records per page << defined above
	*  2:total pages
	*  3:current page (also:$p)
	*  4:record start
	*  5:record end
	*/
	
	$pg = array($count,IMAGEMETA_RECORDS_PER_PAGE,ceil($count/IMAGEMETA_RECORDS_PER_PAGE));
	$p = $_GET['p'] = $pg[3] = (!isset($_GET['p']) || $_GET['p']<1 || $_GET['p']>$pg[2] ? 1 : $_GET['p']);
	$pg[4] = ($pg[1]*$pg[3])-$pg[1]; 
	$pg[5]=(($pg[4]+$pg[1])-1);
	if(($pg[5]+1)>$pg[0]) $pg[5] = ($pg[0]-1);
			
	//build query
	$q =   "SELECT  p.ID as postid, 
					p.post_parent as parentid, 
					p.post_content, 
					p.post_title, 
					p.post_excerpt, 
					p.post_date, 
					pm.meta_value as img,
	
			(SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_ID = postid AND meta_key='_wp_attachment_image_alt' ORDER BY meta_id DESC LIMIT 1) AS alt,
			(SELECT meta_id FROM {$wpdb->prefix}postmeta WHERE post_ID = postid AND meta_key='_wp_attachment_image_alt' ORDER BY meta_id DESC LIMIT 1) AS meta_id
			
			FROM 	{$wpdb->prefix}posts p,{$wpdb->prefix}postmeta pm
			WHERE 	pm.post_ID = p.ID
			AND 	pm.meta_key = '_wp_attached_file'
			AND 	p.post_mime_type IN ('image/gif','image/jpeg','image/png')
			{$imgsearchquery}
			GROUP BY pm.meta_id
			ORDER BY p.{$sort}
			LIMIT {$pg[4]}, {$pg[1]}
			"; 
	
	//verify all images have a paired meta field / create record otherwise
	$imgs = $wpdb->get_results($q, ARRAY_A);
	foreach($imgs as $k=>$i) {
		if($i['meta_id']=="") {
			$wpdb->query("INSERT INTO {$wpdb->prefix}postmeta (meta_id,post_id,meta_key,meta_value) VALUES (NULL,{$i['postid']},'_wp_attachment_image_alt','')");
			}
	}
	//pull 50 rows of images
	$imgs = $wpdb->get_results($q, ARRAY_A);

	//build images table
	echo "<div id='imagemeta'><h1>ImageMeta</h1>
		  <p>ImageMeta is a very light-weight plugin, designed to make updating meta-content (title, caption, description and alternate text) more efficient, by providing one central table of imagery and a duplicate button to copy this information across fields. 
		  Updates are performed immediately after defocusing a field.<br /> 
		  Easily delete images that are neither attached to posts or embedded within their content by clicking the <small><strong>DELETE</strong></small> link provided.</p>";

	//uploads directory size
	exec("du -sh {$uploadsdir}", $size, $duerror);
	if(!$duerror) { list($uploadsdirsize, $notused) = explode("\t",$size[0]); }
	else { $uploadsdirsize = "PHP's exec() function must be enabled to compute directory size."; }
	echo "<p><span>Your uploads directory: <strong>{$uploadsdir}</strong></span> &nbsp; <span>Total Disk Used: <strong>{$uploadsdirsize}</strong></span></p>";

	//warning message
	echo "<div id='warning' class='orange'>".(($cwpp>1||$cwppm>1)?"<strong>NOTE:</strong> ( {$cwpp} ) post records removed. ( {$cwppm} ) postmeta records removed.<br />":"")."</div>";

	// build our pager
	echo "<div class='pager'>
			<span>Page $p <em>(".($pg[4]+1)." - ".($pg[5]+1)." of {$pg[0]})</em>
			</span><ul>";
	for($page=1;$page<=$pg[2];$page++) echo "<li class='pg".($p == $page ? " current" : "")."' rel='{$page}'>{$page}</li>";
	echo "</ul></div><br />";

	echo "<table id='imagemetas'>";
	echo "<thead id='imagemetahead'><tr>
			<th width='100'>
				<span class='sort sort_a ".($_GET['s']=='d'?'selected':'')."' rel='d' title='Sort by Date Ascending'></span>
		Date	<span class='sort sort_d ".($_GET['s']=='d-'?'selected':'')."' rel='d-' title='Sort by Date Descending'></span>
			</th>	
			<th colspan='2'>
				<span  class='sort sort_a ".($_GET['s']=='t'?'selected':'')."' rel='t' title='Sort by Title A-Z'></span>
		Title	<span class='sort sort_d ".($_GET['s']=='t-'?'selected':'')."' rel='t-' title='Sort by Title Z-A'></span>
			</th>
			<th>
				<form id='form_imagemeta' method='GET' action='".IMAGEMETA_URL."'>
					<input type='hidden' name='page' value='imagemeta' readonly />
					<input type='hidden' name='p' id='p' value='{$p}' autocomplete='Off' />
					<input type='hidden' name='s' id='s' value='{$s}' autocomplete='Off' />
					<input type='hidden' name='cleanup' id='cleanup' value='' autocomplete='Off' />
					<input type='search' name='imgsearch' id='imgsearch' value='{$imgsearch}' placeholder='Search Images' autocomplete='Off' />
					<input type='hidden' name='remove' id='remove' value='' autocomplete='Off' />
				</form>
			</th>
		</tr></thead>";

	$missing = array();
	$cp = plugins_url('_img/copyacross.gif', __FILE__);
	$mia = plugins_url('_img/missing.png', __FILE__);
	$rm = plugins_url('_img/delete.png', __FILE__);
	$ed = plugins_url('_img/edit.png', __FILE__);

	$admin = admin_url(); 
	/**********************************************************************************************************************
	* 	Iterate through All Paged Images
	***********************************************************************************************************************/
	foreach($imgs as $k=>$i){
		$lg = ( wp_get_attachment_image_src( $i['postid'], 'original' ) );
		$sm = ( wp_get_attachment_image_src( $i['postid'], 'thumbnail' ) );
		$date = date("m/d/Y",strtotime($i['post_date']));					//get content folder name
		$filepath = get_attached_file($i['postid']); 						//get image file path

	/**********************************************************************************************************************
	* 	Does the Image Exist Still
	***********************************************************************************************************************/
		if(file_exists($filepath)) {
			$rmStyle = ""; $del = "";
			$link = "<a href='{$lg[0]}' target='_blank'><img src='{$sm[0]}' width='60'></a>";
		} else {
			$link = "<img src='{$mia}' />"; $rmStyle = "orange"; $missing[] = $i['postid'];
			$del = "<span class='delete' rel='{$i['postid']}' title='Delete this item'>
						<img src='{$rm}' width='16' height='16' align='absmiddle' />
					</span>&nbsp;";
		}
	$edits = array("a"=>"","e"=>[]);
	$postid = $i['postid'];
	/**********************************************************************************************************************
	* 	Attached Images: get all posts for which this image has been attached.
	***********************************************************************************************************************/
	$ATT = getAllAttached($postid);
	if(!empty($ATT)){
		$edits['a'] = "<li><strong>Attached</strong> (".(count($ATT)).") <select onChange='javascript:edit(this.options[this.selectedIndex].value);'><option value='-1'>- Select to Open -</option>";
		foreach($ATT as $v) $edits['a'].="<option value='{$v['edit']}' >".substr($v['post_title'],0,30)."</option>";
		$edits['a'] .= "</select></li>";
	}
	/**********************************************************************************************************************
	* 	Embedded Images: get all posts for which this image has been embedded, but not the revisions.
	***********************************************************************************************************************/
	$EMB = getAllEmbedded($postid);
	if(!empty($EMB)){
		foreach($EMB as $v){
			if(!empty($edits['e']) && array_key_exists($v['post_parent'],$edits['e']) && $v['post_parent']>0)continue;
			if(!empty($edits['e']) && array_key_exists($v['ID'],$edits['e']) && $v['post_parent']<1)continue;
			if($v['post_parent']<1) $v['post_parent'] = $v['ID'];
			$edits['e'][$v['post_parent']] = "<option value='{$v['post_parent']}' >".substr($v['post_title'],0,30)."</option>";
		}
		$edits['e'] = "<li><strong>Embedded</strong> (".count($edits['e']).") <select onChange='javascript:edit(this.options[this.selectedIndex].value);'><option value='-1'>- Select to Open -</option>".implode("",$edits['e'])."</select></li>";
	} else { 
		$edits['e'] = (string) ""; 
	}
	/**********************************************************************************************************************
	* 	Escape as Needed
	***********************************************************************************************************************/
	foreach($i as $k=>$v){ $i[$k] = esc_attr($v); } //escape
	/**********************************************************************************************************************
	* 	is image used ANYWHERE?
	***********************************************************************************************************************/
	if(count($ATT) + count($EMB) < 1 && $del == ""){
		$purge = "<li><strong>Attached</strong> (0)</li><li><strong>Embedded</strong> (0)</li><li><a class='purge' rel='{$i['postid']}'>DELETE?</a></li>";
	} else { $purge = false; }
	/**********************************************************************************************************************
	* 	Write our Images Table
	***********************************************************************************************************************/
echo <<<EOHTML
	<tr class='info $rmStyle item_{$i['postid']}'>
		<td colspan='4'>
			<ul class='edits'>
				{$purge}
				{$edits['a']}
				{$edits['e']}
			</ul>
			<a href='{$admin}media.php?attachment_id={$i['postid']}&action=edit' title='Edit this item' target='_blank'><img src="$ed" width='16' height=16' align='absmiddle' /></a>
				&nbsp;&nbsp;{$del}
			<b>$date</b>: <i>{$i['img']}</i>
		</td>
	</tr>
	<tr class='row item_{$i['postid']}'>
		<td><div class='thumb'>{$link}</div></td>
		<td>
			<p>Title:</p>  <input type='text' id='post_title:{$i['postid']}' value='{$i['post_title']}' />
			<p>Caption:</p><input type='text' id='post_excerpt:{$i['postid']}' value='{$i['post_excerpt']}' />			
		</td>
		<td width='30' valign='top'><a href='javascript:void(0);' onclick='javascript:copyAcross({$i['postid']},{$i['meta_id']});' title='Copy Title Across'><img src='{$cp}' alt='Copy Title Across' border='0' class='copyAcross' /></a> </td>
		<td>
			<p>Descr:</p>  <input type='text' id='post_content:{$i['postid']}' value='{$i['post_content']}' />
			<p>Alt:</p>    <input type='text' id='meta_value:{$i['meta_id']}' value='{$i['alt']}' />
		</td>
	</tr>
EOHTML;
	}
	echo "</table>";
	/**********************************************************************************************************************
	* 	Pager
	***********************************************************************************************************************/
	echo "<div class='pager'>
			<span>Page $p <em>(".($pg[4]+1)." - ".($pg[5]+1)." of {$pg[0]})</em>
			</span><ul>";
	for($page=1;$page<=$pg[2];$page++) echo "<li class='pg".($p == $page ? " current" : "")."' rel='{$page}'>{$page}</li>";
	echo "</ul></div><br />";
	/**********************************************************************************************************************
	* 	Footer
	***********************************************************************************************************************/
	//Plugin Images
	$pluginIMG = plugins_url('_img/', __FILE__);

	echo "<footer>
	<!-- paypal donations, please -->
	<div class='footer'>
		<div class='donate'>
			<input type='hidden' name='cmd' value='_s-xclick'>
			<input type='hidden' name='hosted_button_id' value='JT8N86V6D2SG6'>
			<input type='image' src='https://www.paypalobjects.com/en_US/i/btn/x-click-but04.gif' border='0' name='submit' alt='PayPal - The safer, easier way to pay online!' class='donate'>
			<p>If <b>ERA404's ImageMeta WordPress Plugin</b> has made your life easier and you wish to say thank you, use the Secure PayPal link above to buy us a cup of coffee.</p>
		</div>
		<div class='bulcclub'>
			<a href='https://www.bulc.club/?utm_source=wordpress&utm_campaign=imagemeta&utm_term=imagemeta' title='Bulc Club. It's Free!' target='_blank'><img src='" . $pluginIMG . "bulcclub.png' alt='Join Bulc Club. It's Free!' /></a>
			<p>For added protection from malicious code and spam, use Bulc Club's unlimited <strong>100% free</strong> email forwarders and email filtering to protect your inbox and privacy. <strong><a href='https://www.bulc.club/?utm_source=wordpress&utm_campaign=imagemeta&utm_term=imagemeta' title='Bulc Club. It's Free!' target='_blank'>Join Bulc Club &raquo;</a></strong></p>
		</div>
	</div>
	</div>
	<footer><small>See more <a href='http://profiles.wordpress.org/era404/' title='WordPress plugins by ERA404' target='_blank'>WordPress plugins by ERA404</a> or visit us online: <a href='http://www.era404.com' title='ERA404 Creative Group, Inc.' target='_blank'>www.era404.com</a>. Thank you for using ImageMeta.</small></footer>
	</footer></div>";

	$pluginurl = IMAGEMETA_URL;
	echo "<script type='text/javascript'>
	var pluginurl = '{$pluginurl}';
	var p={$pg[2]}, s='{$s}';
	console.log(p,s);
	function edit(postid){
		if(postid<1) return;
		window.open('{$admin}post.php?post='+postid+'&action=edit');
		return;
	}";
	//show missing images warning
	if(count($missing)>0){
$missingobj = json_encode($missing);
$missingcount = count($missing);
echo <<<EOWARNING
	var warning = document.getElementById('warning');
	warning.innerHTML += "<strong>NOTE:</strong> ( {$missingcount} ) image file(s) on this page do not exist in your uploads directory. ";
	warning.innerHTML += "You can delete these images individually using the links below or <a class='cleanup'>delete all {$missingcount} at once</a>.";
	var missing = {$missingobj};
	jQuery("#warning").show();
EOWARNING;
	}
	//show removed image warning
	if($cwpp+$cwppm>0){ echo "jQuery('#warning').show();"; }
	echo "</script>";
}
?>
<?php 
/**************************************************************************************************
*	Some useful functions
*	getAllAttached: Get All of the Posts/Pages (etc) where this image is attached
*	getAllEmbedded: Get All of the Posts/Pages (etc) where this image is embedded
**************************************************************************************************/
function getAllAttached($postid, $anywhere=false){
	global $wpdb;
	if(!$anywhere){
		$qATT = "SELECT p.post_title,pm.post_id as edit 
				FROM {$wpdb->prefix}postmeta pm,{$wpdb->prefix}posts p
				WHERE pm.post_id = p.ID
				AND (
                        ( pm.meta_key = '_thumbnail_id' AND pm.meta_value = $postid ) OR  
                        ( pm.meta_key = '_product_image_gallery' AND CONCAT(',',pm.meta_value,',') LIKE '%,{$postid},%' )
                )
				AND p.post_status != 'trash'
				ORDER BY p.post_date DESC";			
	} else {
		$qATT = "SELECT p.ID, p.post_parent, p.post_type, p.post_status, p.post_title, pm.post_id as edit 
				FROM {$wpdb->prefix}postmeta pm,{$wpdb->prefix}posts p
				WHERE ( pm.post_id = p.ID OR pm.post_id = p.post_parent )
				AND (
                        ( pm.meta_key = '_thumbnail_id' AND pm.meta_value = $postid ) OR  
                        ( pm.meta_key = '_product_image_gallery' AND CONCAT(',',pm.meta_value,',') LIKE '%,{$postid},%' )
                )
				ORDER BY p.post_date DESC"; 
	}
																	//echo "<br />$qATT";
	$ATT = $wpdb->get_results($qATT, ARRAY_A); 						//myprint_r($ATT);
	return($ATT);
}
//anywhere would ALSO INCLUDE revisions, drafts, trash
function getAllEmbedded($postid,$anywhere=false){
	global $wpdb;
	//modern: wp-image-123
	//legacy: id='image123'  /   (or the image path)
	//gallery: [gallery ids='123']
	preg_match("/^(.*\/)(.*)(\.)([^\.]+)$/", $wpdb->get_var("SELECT guid FROM {$wpdb->prefix}posts WHERE ID={$postid}"), $image);
	$qEMB = "SELECT p.ID, p.post_title, p.post_parent".($anywhere?",p.post_type,p.post_status":"")."
			 FROM {$wpdb->prefix}posts p
			 WHERE ( ".
			  "p.post_content LIKE '%wp-image-{$postid}%' 
			   OR p.post_content REGEXP '\\\<img([^\>]+)id\\\=\\\"image{$postid}\\\"'
			   OR p.post_content REGEXP '\\\[gallery(.*)ids\\\=(\\\,|\\\"){$postid}(\\\,|\\\")([^\]]*)\\\]' ".
			   (count($image)==5?" OR p.post_content REGEXP '{$image[1]}{$image[2]}(.*)?.{$image[4]}' ":"").
			" ) ".
			($anywhere ? "" : " AND p.post_type != 'revision' AND p.post_status= 'publish' ")."
			ORDER BY p.post_date DESC";												//echo "<br />$qEMB";
	$EMB = $wpdb->get_results($qEMB, ARRAY_A); 										//myprint_r($EMB);
	return($EMB);
}
/**************************************************************************************************
*	AJAX Functions
*	updatedb: update the meta properties for the image
**************************************************************************************************/
function ajax_updatedb() {
	global $wpdb;
	
	//build query from passed vars
	$fval = $_POST['fval'];
	$fname = $_POST['fname'];
	$q = "UPDATE ".(substr($fname[0],0,4)=="post"?"{$wpdb->prefix}posts":"{$wpdb->prefix}postmeta").
		" SET {$fname[0]} = '{$fval}' WHERE ".
		(substr($fname[0],0,4)=="post"?"ID":"meta_id")." = {$fname[1]}";
		
	print_r($_POST); echo "Query: $q";
	
	$wpdb->query($q);
	die(); // stop executing script
}
/**************************************************************************************************
*	AJAX Functions:
*	purge: erase all unused images (and variants), after providing a detailed confirmation message
**************************************************************************************************/
function ajax_purge(){
	global $wpdb;

	//we need a valid image id (postid)
	if((int) trim($_POST['postid']) != $_POST['postid']) imagemeta_appResponse(400,"Invalid Image ID");
	$postid = (int) trim($_POST['postid']);

	$wpposts = "{$wpdb->prefix}posts";
	$wppostmeta = "{$wpdb->prefix}postmeta";
	$cq =  "SELECT meta_value FROM $wpposts, $wppostmeta
			WHERE 		{$wppostmeta}.post_ID = {$wpposts}.ID
			AND 		{$wppostmeta}.meta_key ='_wp_attached_file'
			AND 		{$wpposts}.ID = {$postid}
			GROUP BY 	{$wpposts}.ID";
	$imgpath = $wpdb->get_var($cq);
	//we need a valid image file
	if("" == $imgpath) imagemeta_appResponse(400,"Image File Not Found");

	$uploads = wp_upload_dir();
	$uploadsdir = $uploads['basedir']."/";
	//we need a valid image path, or we can't continue
	if(strstr($imgpath,$uploadsdir)) $imgpath = str_replace($uploadsdir,"",$imgpath);
	if(!preg_match("/^(.*\/)(.*)(\.)([^\.]+)$/", $uploadsdir . $imgpath, $imgfile)) imagemeta_appResponse(400,"Malformed Image File");

	/**************************************************************************************************
	*	Query for all attached or embedded images, even if in trash/revision/draft
	**************************************************************************************************/
	$editurl = get_admin_url(  );
	$confirms = array("resp"=>"confirm",
					  "all"=>0,
					  "emb"=>array("rev"=>array(),"tra"=>array(),"dra"=>array()),
					  "att"=>array("rev"=>array(),"tra"=>array(),"dra"=>array()));
	$ATT = getAllAttached($postid,"ANYWHERE");			//echo "ATTACHED:<br />"; myprint_r($ATT);
	$EMB = getAllEmbedded($postid,"ANYWHERE");			//echo "EMBEDDED:<br />"; myprint_r($EMB);

	/**************************************************************************************************
	*	Sort matching embeds into an array for a logical confirmation message
	**************************************************************************************************/
	if(!empty($EMB)){
		foreach($EMB as $p){
			$editid = ($p['post_parent']<1?$p['ID']:$p['post_parent']);
			if($p['post_type'] == "page"){
				if($p['post_status'] == "trash"){
					if(!isset($confirms['emb']['tra'][$editid])){
						$confirms['emb']['tra'][$editid] = "<a href='{$editurl}edit.php?post_status=trash&post_type=page' target='_blank'>{$p['post_title']}</a>";
						$confirms['all']++;
					}
				}
			} elseif($p['post_type'] == "post"){
				if($p['post_status'] == "trash"){
					if(!isset($confirms['emb']['tra'][$editid])){
						$confirms['emb']['tra'][$editid] = "<a href='{$editurl}edit.php?post_status=trash&post_type=post' target='_blank'>{$p['post_title']}</a>";
						$confirms['all']++;
					}
				}
			} elseif($p['post_type'] == "revision"){
				if(!isset($confirms['emb']['tra'][$editid])){
					$confirms['emb']['rev'][$editid] = "<a href='{$editurl}post.php?post={$editid}&action=edit' target='_blank'>{$p['post_title']}</a>";
					$confirms['all']++;
				}
			} 
		}
	}
	/**************************************************************************************************
	*	Sort matching attachments into an array for a logical confirmation message
	**************************************************************************************************/
	if(!empty($ATT)){
		foreach($ATT as $p){
			$editid = ($p['post_parent']<1?$p['ID']:$p['post_parent']);
			if($p['post_type'] == "page"){
				if($p['post_status'] == "trash"){
					if(!isset($confirms['att']['tra'][$editid])){
						$confirms['att']['tra'][$editid] = "<a href='{$editurl}edit.php?post_status=trash&post_type=page' target='_blank'>{$p['post_title']}</a>";
						$confirms['all']++;
					}
				}
			} elseif($p['post_type'] == "post"){
				if($p['post_status'] == "trash"){
					if(!isset($confirms['att']['tra'][$editid])){
						$confirms['att']['tra'][$editid] = "<a href='{$editurl}edit.php?post_status=trash&post_type=post' target='_blank'>{$p['post_title']}</a>";
						$confirms['all']++;
					}
				}
			} elseif($p['post_type'] == "revision"){
				if(!isset($confirms['att']['tra'][$editid])){
					$confirms['att']['rev'][$editid] = "<a href='{$editurl}post.php?post={$editid}&action=edit' target='_blank'>{$p['post_title']}</a>";
					$confirms['all']++;
				}
			} 
		}
	}

	/**************************************************************************************************
	*	Construct the confirmation message
	**************************************************************************************************/
	$confirmmessage = "";
	if($confirms['all'] > 0){
		$confirmmessage = "<strong>Image used in ({$confirms['all']}) instances:</strong>";
		foreach($confirms['emb'] as $type=>$arr){
			switch($type){
				case "rev": $typestring = "Embedded in Revision"; break;
				case "tra": $typestring = "Embedded in Trash"; break;
				case "dra": $typestring = "Embedded in Drafts"; break;
			}
			if(!empty($arr)){
				$confirmmessage .= "<br />(".count($arr).") {$typestring}:<ul>";
				$confirmmessage .= "<li>".implode("</li><li>",$arr)."</li></ul>";
			}
		}
		foreach($confirms['att'] as $type=>$arr){
			switch($type){
				case "rev": $typestring = "Attached in Revision"; break;
				case "tra": $typestring = "Attached in Trash"; break;
				case "dra": $typestring = "Attached in Drafts"; break;
			}
			if(!empty($arr)){
				$confirmmessage .= "<br />(".count($arr).") {$typestring}:<ul>";
				$confirmmessage .= "<li>".implode("</li><li>",$arr)."</li></ul>";
			}
		}
	}

	/**************************************************************************************************
	*	And include all the variant image sizes, for a visual confirmation
	*	Try with exec() first, since it's much faster than reading the directory
	**************************************************************************************************/
	$sisters = array();
	$lsgrep = "ls {$imgfile[1]} | egrep '{$imgfile[2]}\-([0-9]+x[0-9]+)?\.{$imgfile[4]}'"; //echo "LSGREP: $lsgrep";
	exec($lsgrep, $sisters, $lserror);
	if($lserror){
		if($handle = opendir($imgfile[1])){
			$prefix = preg_quote("{$imgfile[2]}-","/");
			$suffix = ".{$imgfile[4]}";
			while(false !== ($file = readdir($handle))){
				if(preg_match("/^{$prefix}([\d]+x[\d]+){$suffix}$/", $file)) $sisters[] = $file;
			}
			closedir($handle);
		}
	}
	sort($sisters);
	$images = array_merge( array($imgfile[2].".".$imgfile[4]), $sisters);

	/**************************************************************************************************
	*	if !confirm ... return a confirmation message to be approved by the user
	**************************************************************************************************/
	if(!isset($_POST['confirmed']) || $_POST['confirmed']=="false"){
		
		//filesizes
		$totalbytes = 0;
		foreach($images as $ik => $i){
			$bytes = filesize($imgfile[1].$i);
			$images[$ik] = $i . "<span>".human_filesize($bytes)."</span>";
			$totalbytes += $bytes;
		}
		$confirmmessage .= "The following image variants will be deleted".($totalbytes > 0 ? " and ".human_filesize($totalbytes)." will be recovered:" : ":")."<ul>";
		$confirmmessage .= "<li>".implode("</li><li>",$images)."</li></ul>";

		$_POST['confirm'] = $confirmmessage;
		header('Content-type: application/json');
		die(json_encode($_POST));
	}
	/**************************************************************************************************
	*	Otherwise, continue with the deletes
	**************************************************************************************************/
	//filesizes
	$totalbytes = 0;
	foreach($images as $ik => $i) $totalbytes += filesize($imgfile[1].$i);

	$results = array(); $errors = false;
	foreach($images as $img){
		if(@unlink("{$imgfile[1]}{$img}")){ $results[]="<strong>Deleted:</strong> {$img}"; }
		else { 	$results[]="{$img} could not be deleted.";
				$errors = true;
		}
	}
	if($errors < 1){
		$rwpp = "DELETE FROM {$wpposts} WHERE ID={$postid}"; 
		$wpdb->query($rwpp);
		$rwppm = "DELETE FROM {$wppostmeta} WHERE post_id={$postid}"; 
		$wpdb->query($rwppm);
	}
	header('Content-type: application/json');
	die(json_encode(
		array(	"results"	=> "<ul><li>".implode("</li><li>",$results)."</li>".
								($errors?"</ul>":"<li><strong>Recovered:</strong> ".human_filesize($totalbytes)."</li></ul>"),
				"errors"	=> ($errors?1:0),
				"row" 		=> ".item_{$postid}" )
	));
}
/**************************************************************************************************
*	For Confirmation Message
**************************************************************************************************/
function human_filesize($bytes, $decimals = 2) {
    $size = array('B','K','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}
/**************************************************************************************************
*	Erases an Image from the Database, when it doesn't exist on disk
**************************************************************************************************/
function cleanup($postid){
	global $wpdb;
	$cwpp = 0; $cwppm = 0;
		
	$qwpp = "SELECT count(ID) FROM {$wpdb->prefix}posts WHERE ID={$postid}"; $cwpp = $wpdb->get_var($qwpp);
	$rwpp = "DELETE FROM {$wpdb->prefix}posts WHERE ID={$postid}"; $wpdb->query($rwpp);
	$qwppm = "SELECT count(meta_id) FROM {$wpdb->prefix}postmeta WHERE post_id={$postid}"; $cwppm = $wpdb->get_var($qwppm);
	$rwppm = "DELETE FROM {$wpdb->prefix}postmeta WHERE post_id={$postid}"; $wpdb->query($rwppm);

	return(array($cwpp,$cwppm));
}
function ajax_cleanup(){
	$cwpp = 0; $cwppm = 0;
	foreach($_POST['ids'] as $postid){
		if(is_numeric($postid) && $postid>0) {
			list($p,$pm) = cleanup((int) $postid);
			$cwpp+=$p; $cwppm+=$pm;
		}
	}
	header('Content-Type: application/json');
	die(json_encode(array("cleanup"=>"{$cwpp}|{$cwppm}")));
}
/**************************************************************************************************
*	Responses
**************************************************************************************************/
function imagemeta_appResponse($code=400, $text="Bad Request"){
	$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
	header($protocol . ' ' . $code . ' ' . $text);
	$GLOBALS['http_response_code'] = $code;
	die("Bad Request".($text=="Bad Request"?"":". {$text}."));
}
if(!function_exists("myprint_r")){
	function myprint_r($in,$textarea=false){
		if($textarea){ echo "<textarea style='width:60%; height:200px;'>".print_r($in,true)."</textarea>"; return; }
		echo "<pre>"; print_r($in); echo "</pre>"; return;
	}
}
?>