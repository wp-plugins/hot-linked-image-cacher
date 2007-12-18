<?php
/*
Plugin Name: Hot Linked Image Cacher
Plugin URI: http://www.linewbie.com/wordpress-plugins/
Description: Goes through your posts and gives you the option to cache some or all hotlinked images locally in the upload folder of this plugin
Version: 1.0
Author: Jason W.
Author URI: http://www.linewbie.com
WordPress Version Required: 1.5
*/

/*
Copyright (C) 2007 Linewbie.com

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

function hi_mkdirr($pathname, $mode = 0777) { // Recursive, Hat tip: PHP.net
	// Check if directory already exists
	if ( is_dir($pathname) || empty($pathname) )
		return true;

	// Ensure a file does not already exist with the same name
	if ( is_file($pathname) )
		return false;

	// Crawl up the directory tree
	$next_pathname = substr( $pathname, 0, strrpos($pathname, DIRECTORY_SEPARATOR) );
	if ( hi_mkdirr($next_pathname, $mode) ) {
		if (!file_exists($pathname))
			return mkdir($pathname, $mode);
	}

	return false;
}

function hi_mm_ci_add_pages() {
	
	add_management_page('Hot Linked Image Cacher Plugin', 'Hotlinked Image Cacher', 8, __FILE__,
'hi_mm_ci_manage_page');
}


function hi_mm_ci_manage_page() {
	global $wpdb;
	$debug = 0;
	$httppath = get_option('siteurl') . "/wp-content/plugins/hotlinked-image-cacher/upload";
	$tophttp = get_option('siteurl');
	if($debug==1){
	echo $httppath." is the url for this site<br />";
  }
	$absoupload = ABSPATH . "/wp-content/plugins/hotlinked-image-cacher/upload";
	if($debug==1) {
		echo $absoupload . " is the absolute path<br />";
	}

?>
<div class="wrap">
<h2>Hotlinked Image Caching</h2>
<?php if ( !isset($_POST['step']) ) : ?>
<p>Here's how this plugin works:</p>
<ol>
	<li>Enter the <b>post id</b> for the post you need to perform image cache on.</li>
	<li>If you need to perform image cache on all post, then put <b>all</b> in the post id field.</li>
	<li>Then you'll be presented with a list of domains, check the domains you want to grab the image from.</li>
	<li>The images will be copied to your upload directory (this directory is under your hotlinked-image-cacher plugin directory and must be writable).</li>
	<li>The img links in your posts will be updated to their new local url location automatically.</li>
</ol>
<form action="" method="post">
<p class="submit">
	<div align="left">
	Choose from one of the following methods to grab remote image, <b>curl</b> is more secure. If your server support both method, choose <b>curl</b>. <br />
	The default will be using <b>curl</b>, you must choose <b>allow_url_fopen</b> if your server do not support <b>curl</b> but enabled <b>allow_url_fopen</b> in php.ini.<br /><br />
	<input type="radio" name="urlmethod" value="curl" checked="checked"> <b>curl</b> (choose this method if your server support <b>curl</b>)<br />
	<input type="radio" name="urlmethod" value="allow_url_fopen"> <b>allow_url_fopen</b> (choose this method if your server allow remote fopen AND does not support <b>curl</b>) <br /><br />
  </div>
	Post ID: <input name="postid" type="text" id="postid" value="enter a post id here">
	<input name="step" type="hidden" id="step" value="2">
	<input type="submit" name="Submit" value="Get Started &raquo;" />
</p>
</form>
<?php endif; ?>

<?php 
$urlmethod = $_POST['urlmethod'];
$postidnum = $_POST['postid'];
if ('2' == $_POST['step']) : ?>
<?php
if ($postidnum == 'all' || $postidnum == 'All' || $postidnum == 'ALL' || $postidnum == 'enter a post id here') {
$posts = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE post_content LIKE ('%<img%')");
if ( !$posts ) 
	die('No posts with images were found.');
}
else {
$posts = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE ID LIKE $postidnum");
if ( !$posts ) 
	die('No posts with this Post ID were found.');
}


if($debug==1){
	echo $postidnum." was the post ID chosen<br />";
}

foreach ($posts as $post) :
	preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $post->post_content, $matches);
	
	foreach ($matches[1] as $url) :
			if($debug==1){
			echo $url;
			echo $httppath;
		  }
		  $op2 = stristr( $url, $tophttp );
			if ( $op2 === false ){
				$msg = 'NOT LOCAL';
				if($debug==1){
				echo $msg;
			  }
			} else {
				continue; // Already local
			}
		$url = parse_url($url);

		$url['host'] = str_replace('www.', '', $url['host']);
		$domains[$url['host']]++;
	endforeach;

endforeach;
?>
<p>Check the domains that you want to grab images from:</p>
<form action="" method="post">
<ul>
<?php
if (!is_null($domains)) { 
foreach ($domains as $domain => $num) : 
?>
	<li>
		<label><input type="checkbox" name="domains[]" value="<?php echo $domain; ?>" /> <code><?php echo $domain; ?></code> (<?php echo $num; ?> images found)</label>
	</li>
<?php endforeach; } ?>
</ul>
<p class="submit">
	<input name="urlmethod" type="hidden" id="urlmethod" value="<?php echo $urlmethod; ?>" />
	<input name="postid" type="hidden" id="postid" value="<?php echo $postidnum; ?>" />
	<input name="step" type="hidden" id="step" value="3" />
	<input type="submit" name="Submit" value="Cache These Images &raquo;" />
</p>
</form>
<?php endif; ?>

<?php if ('3' == $_POST['step']) : ?>
<?php
$urlmethod = $_POST['urlmethod'];
$postidnum = $_POST['postid'];
if($debug==1){
	echo $urlmethod." is the current url method<br />";
	echo $postidnum." is the current post ID<br />";
}
if ( !isset($_POST['domains']) )
	die("You didn't check any domains, did you change your mind?");
if ( !is_writable($absoupload) )
	die('Your upload folder is not writable, chmod 777 on the folder /wp-content/plugins/hotlinked-image-cacher/upload/');

foreach ( $_POST['domains'] as $domain ) :
	if($debug==1){
	echo $postidnum." is the post ID chosen, now going to rewrite urls<br />";
  }
	if ($postidnum == 'all' || $postidnum == 'All' || $postidnum == 'ALL') {
	$posts = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE post_content LIKE ('%<img%') AND post_content LIKE ('%$domain%')");
  }
	else {
	$posts = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE ID LIKE $postidnum");
  }
?>
<h3><?php echo $domain; ?></h3>

<ul>
<?php 
	foreach ($posts as $post) :
		preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $post->post_content, $matches);
		foreach ( $matches[1] as $url ) :
		  $op1 = stristr( $url, $tophttp);
			if ( $op1 === false ){
				$msg = 'NOT LOCAL';
			} else {
				continue; // Already local
			}
			$filename = str_replace('%20', 'spa', basename ( $url ));
			$b        = parse_url( $url );
			$dir      = $absoupload . '/' . $domain . dirname ( $b['path'] );
	
			hi_mkdirr( $dir );
			$f        = fopen( $dir . '/' . $filename , 'w' );
			if($urlmethod="curl" || is_null($urlmethod)){
			$url      = $b['scheme'] . '://' . $b['host'] . str_replace(' ', '%20', $b['path']) . $b['query'];
			$ch = curl_init();
      $timeout = 5;
      curl_setopt($ch, CURLOPT_URL, $url);
		  curl_setopt($ch, CURLOPT_HEADER, 0);
		  curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		  curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		  $img = curl_exec($ch);
		  curl_close($ch);
		  }
		  else{
			$img = file_get_contents( $b['scheme'] . '://' . $b['host'] . str_replace(' ', '%20', $b['path']) . $b['query'] );
		  }
		  
			if ( $img ) {
				fwrite( $f, $img );
				fclose( $f );
				$local = $httppath . '/' . $domain . dirname ( $b['path'] ) . "/$filename";
				$wpdb->query("UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '$url', '$local');");
				echo "<li>Cached $url</li>";
				flush();
			}
		endforeach;
	endforeach;
?>
</ul>
<?php
endforeach;
?>
<h3>All done!</h3>
<?php endif; ?>
</div>
<?php
}

add_action('admin_menu', 'hi_mm_ci_add_pages');

?>