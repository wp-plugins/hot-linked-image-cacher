<?php
/*
Plugin Name: Hot Linked Image Cacher
Plugin URI: http://www.linewbie.com/wordpress-plugins/
Description: Goes through your posts and gives you the option to cache some or all hotlinked images locally in the upload folder of this plugin
Version: 1.16
Author: linewbie
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


#
#  This regexp pattern is used to find the
#  <img src="xxxx"> links in posts.
#
define('HLIC_IMG_SRC_REGEXP', '|<img.*?src=[\'"](.*?)[\'"].*?>|i');

function hlic_mkdirr($pathname, $mode = 0777) { // Recursive, Hat tip: PHP.net
	// Check if directory already exists
	if ( is_dir($pathname) || empty($pathname) )
		return true;

	// Ensure a file does not already exist with the same name
	if ( is_file($pathname) )
		return false;

	// Crawl up the directory tree
	$next_pathname = substr( $pathname, 0, strrpos($pathname, DIRECTORY_SEPARATOR) );
	if ( hlic_mkdirr($next_pathname, $mode) ) {
		if (!file_exists($pathname)) {
			$rtn = mkdir($pathname, $mode);
			return $rtn;
		}
	}

	return false;
}

function hlic_mm_ci_add_pages() {
	
	add_management_page('Hot Linked Image Cacher Plugin', 'Hotlinked Image Cacher', 8, __FILE__,
'hlic_mm_ci_manage_page');
}

function hlic_mm_ci_manage_page() {
	global $wpdb;
	$debug = 0;

	$abs_upload_dir = hlic_abs_upload_dir();
	$upload_dir = hlic_upload_dir();

	if ($debug==1) {
		echo $abs_upload_dir . " is the absolute path<br />";
	}

	$my = parse_url(get_option('siteurl'));
	$my_host = $my['host'];

?>
<div class="wrap">
<h2>Hotlinked Image Caching</h2>
<?php
if ( !isset($_POST['step']) ) {
?>
<? if (0) { # jjj ?>
<p>jjj upload_dir = <?=$upload_dir?></p>
<p>jjj abs_upload_dir = <?=$abs_upload_dir?></p>
<? } ?>
<p>Here's how this plugin works:</p>
<ol>
	<li>After you make a post that contains images from another website (&quot;hot-linked&quot; images), it will automatically download those images and save a local copy.  If the download is successful, then the &lt;img src=...&gt; links in your posts will be updated to reference the new local copy of the image.</li>
	<li>Cached images will be copied to your local <b><?=$upload_dir?></b> directory (this directory must be writable).</li>
	<li>Please note that once an image is cached, this is NOT REVERSIBLE.  If you remove or disable this plugin, the &lt;img src=...&gt; links in your posts will still reference the local cache copy.</li>
	<li>To cache the images for an existing post, enter the <b>post id</b> in the field below.</li>
	<li>If you want to perform image cache on all posts, then enter <b>ALL</b> in the post id field.</li>
	<li>Then you'll be presented with a list of domains, just check the domains with images you want to cache.</li>
</ol>
<form action="" method="post">
<p class="submit">
	<div align="left">
	<b>curl</b> is the recommended method for downloading images.  However, if curl is not supported, select <b>allow_url_fopen</b> and make sure that <b>allow_url_fopen</b> is enabled in your php.ini file<br /><br />
	<input type="radio" name="urlmethod" value="curl" checked="checked"> <b>curl</b> (choose this method if your server support <b>curl</b>)<br />
	<input type="radio" name="urlmethod" value="allow_url_fopen"> <b>allow_url_fopen</b> (choose this method if your server allows remote fopen AND does not support <b>curl</b>) <br /><br />
  </div>
	Post ID: <input name="postid" type="text" id="postid" value="enter a post id here">
	<input name="step" type="hidden" id="step" value="2">
	<input type="submit" name="Submit" value="Get Started &raquo;" />
</p>
</form>
<?php
}
?>

<?php 
$urlmethod = trim($_POST['urlmethod']);
$postidnum = trim($_POST['postid']);

if ('2' == $_POST['step']) {
	if (strtoupper($postidnum) == 'ALL' || $postidnum == 'enter a post id here') {
		$postid_list = $wpdb->get_results("SELECT DISTINCT ID FROM $wpdb->posts WHERE post_content LIKE ('%<img%')");
	if ( !$postid_list ) 
		die('No posts with images were found.');
	} else {
		$postid_list = $wpdb->get_results("SELECT DISTINCT ID FROM $wpdb->posts WHERE ID = '$postidnum'");
		if ( !$postid_list ) 
			die('No posts with this Post ID were found.');
	}

	if ($debug==1) {
		echo $postidnum." was the post ID chosen<br />";
	}

	$img_processed = "";

	foreach ($postid_list as $v) {
		$postid = $v->ID;
		$post = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE ID = '$postid'");
		$post_content = $post[0]->post_content;

		preg_match_all(HLIC_IMG_SRC_REGEXP, $post_content, $matches);
	
		foreach ($matches[1] as $url) {
			if ($debug==1) {
				echo "url=$url<br>";
			}
			$b = parse_url($url);

			if (!$b['host'] || (strcasecmp($b['host'], $my_host) == 0)) {
				continue;  # local img reference
			}

			#
			#  Count each img url only ONCE
			#
			if ($url && !$img_processed[$url]) {
				$domains[$b['host']]++;
				$img_processed[$url] = true;
			}
		}
	}
?>
<?php
	if (is_null($domains)) { 
		die('All images appear to have been cached -- nothing to do.');
	} else {
?>
<p>Check the domains that you want to grab images from:</p>
<form action="" method="post">
<ul>
<?php
		foreach ($domains as $domain => $num) { 
?>
	<li>
		<label><input type="checkbox" name="domains[]" value="<?php echo $domain; ?>" /> <code><?php echo $domain; ?></code> (<?php echo $num; ?> images found)</label>
	</li>
<?php
		}
?>
</ul>
<p class="submit">
	<input name="urlmethod" type="hidden" id="urlmethod" value="<?php echo $urlmethod; ?>" />
	<input name="postid" type="hidden" id="postid" value="<?php echo $postidnum; ?>" />
	<input name="step" type="hidden" id="step" value="3" />
	<input type="submit" name="Submit" value="Cache These Images &raquo;" />
</p>
</form>
<?php
	}
}
?>

<?php
if ('3' == $_POST['step']) {

	$urlmethod = trim($_POST['urlmethod']);
	$postidnum = trim($_POST['postid']);

	if ($debug==1) {
		echo $urlmethod." is the current url method<br />";
		echo $postidnum." is the current post ID<br />";
	}
	if ( !isset($_POST['domains']) )
		die("You didn't check any domains, did you change your mind?");

	hlic_mkdirr($abs_upload_dir);

	if ( !is_writable($abs_upload_dir) )
		die('Your upload folder is not writable, chmod 777 on the folder '.$abs_upload_dir);

	foreach ( $_POST['domains'] as $domain ) {
		if ($debug==1) {
			echo $postidnum." is the post ID chosen, now going to rewrite urls<br />";
		}

		if (strtoupper($postidnum) == 'ALL') {
			$domain_url = 'http://'.$domain;
			$qry = "SELECT DISTINCT ID FROM $wpdb->posts WHERE post_content REGEXP '[[.less-than-sign.]]img[[:blank:]]+[^[.greater-than-sign.]]*src=[[.apostrophe.][.quotation-mark.]]".preg_quote($domain_url)."([^[.apostrophe.][.quotation-mark.]]+)[[.apostrophe.][.quotation-mark.]][^[.greater-than-sign.]]*[[.greater-than-sign.]]'";
			if ($debug==1) {
				echo "qry=$qry<br />";
			}
			$postid_list = $wpdb->get_results($qry);
		} else {
			$postid_list = $wpdb->get_results("SELECT DISTINCT ID FROM $wpdb->posts WHERE ID = '$postidnum'");
		}
?>
<h3>
<?php
		echo $domain;
?></h3>
<ul>
<?php 

		$opts['match_domain'] = $domain;
		$opts['urlmethod'] = $urlmethod;
		$opts['show_progress'] = true;

		#
		#  Cache the images for each post in the list
		#
		foreach ($postid_list as $v) {
			hlic_cache_img($v->ID, $opts);
		}
?>
</ul>
<h3>All done!</h3>
<?php
	}
}
?>
</div>
<?php
}

# jjj - use WP functions to save/retrieve option setting
#  store in wp_options table
#    blog_id  int(11)  (normally 0)
#    option_name varchar(64)  (make it 'hlic_xxxx')
#    option_value longtext
# 
#
function hlic_cache_img($postid, $opts) {
	global $wpdb;

	#
	#  If the $create_md5_filename flag is true,
	#  then create unique "anonymous" filenames for
	#  each image. The filename is based on an
	#  MD5 checksum of the file contents. Example:
	#    http://www.mydomain.com/wp-content/uploads/HLIC/4105c83ca00b0e2801bc601f93a9d63e.gif	
	#
	#  If false, it will create a long
	#  filename based on the original 
	#  path. Example:
	#    http://www.mydomain.com/wp-content/uploads/HLIC/www.olddomain.com/images/imagename.gif
	#
	$create_md5_filename = true;

	$min_img_size = 20;  # minimum size of image file

	static $suffix_map = array (
		'image/gif'   => 'gif',
		'image/jpeg'  => 'jpg',
		'image/jpg'   => 'jpg',
		'image/png'   => 'png',
		'image/x-png' => 'png');

	$my = parse_url(get_option('siteurl'));
	$my_host = $my['host'];

	$abs_upload_dir = hlic_abs_upload_dir();
	$upload_dir = hlic_upload_dir();
	$httppath = get_option('siteurl') . "/".$upload_dir;

	$post = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE ID = '$postid'");
	$post_content = $post[0]->post_content;

	preg_match_all(HLIC_IMG_SRC_REGEXP, $post_content, $matches);
			
	$img_processed = "";
	foreach ( $matches[1] as $url ) {
		$img_url = $url;
		$dummy2 = str_replace('http://', '', $url);
		$dummy3 = str_replace('//', '/', $dummy2);
		$dummy4 = 'http://'.$dummy3;
		$url = $dummy4;

		$b = parse_url($url);

		if (!$b['host'] || (strcasecmp($b['host'], $my_host) == 0)) {
			continue;  # local img reference
		}

		if (!$opts['match_domain'] || $b['host'] == $opts['match_domain']) {

			if ($img_processed[$img_url]) {
				continue;  # we've already processed this one
			}

			if ($b['query']) {
				$url = $b['scheme'] . '://' . $b['host'] . str_replace(' ', '%20', $b['path']).'?'.$b['query'];
			} else {
				$url = $b['scheme'] . '://' . $b['host'] . str_replace(' ', '%20', $b['path']);
			}

			$img = "";
			$filename = "";

			if ($opts['urlmethod'] == "curl" || is_null($opts['urlmethod'])) {
				$ch = curl_init();
				$timeout = 5;

				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_HEADER, 0);
			
				# set referer to home page of the same site
				$referer = $b['scheme'] . '://' . $b['host'] . '/';
				curl_setopt($ch, CURLOPT_REFERER, $referer);
				curl_setopt($ch, CURLOPT_AUTOREFERER, true);

				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.6pre) Gecko/2009011606 Firefox/3.1');
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

				$img = curl_exec($ch);
				$info = curl_getinfo($ch);
				curl_close($ch);

				#
				#  If this is a "dynamic" URL image,
				#  then create a filename from the MD5
				#  checksum of the image data.  Determine
				#  filename suffix from mime content type.
				#
				if ($b['query'] || $create_md5_filename) {
					$content_type = strtolower($info['content_type']);
					$suffix = $suffix_map[$content_type];

					if ($suffix) {
						$filename = md5($img) . ".$suffix";
					} else {
						$img = "";  # unable to determine suffix
					}
				}
			} else {
				#
				#  curl not available
				#
				$img = file_get_contents($url);
			}

			if (!$filename) {
				$filename = str_replace('%20', 'spa', basename($url));
				$p = pathinfo($b['path']);
				$suffix = $p['extension'];

				if (!preg_match('/^(gif|jpeg|jpg|png)$/', $suffix)) {
					$img = "";  # invalid suffix for graphics file
					if ($opts['show_progress']) {
						echo "<li>WARNING: not cached, unable to determine file type of: $img_url</li>";
						flush();
					}
				}
			}
		
			if ($img) {
				if ($create_md5_filename) {
					$dir = $abs_upload_dir;
				} else {
					$dir = $abs_upload_dir.'/'.$b['host'].dirname($b['path']);
				}
				$dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
	
				hlic_mkdirr($dir);
				$pathname = $dir.DIRECTORY_SEPARATOR.$filename;
				$f = fopen($pathname , 'w' );

				if ($f) {
					$img_size = strlen($img);
					$write_size = fwrite($f, $img);

					#
					#  Make sure that read/write counts match and
					#  we have enough data to be an image file.
					#
					if (($write_size == $img_size) && ($img_size > $min_img_size) && ($write_size > $min_img_size)) {
						$img_saved = true;
					} else {
						$img_saved = false;
					}
					fclose($f);

					if ($create_md5_filename) {
						$local_img_url = $httppath . "/$filename";
					} else {
						$local_img_url = $httppath . '/' . $b['host'] . dirname($b['path']) . "/$filename";
					}

					#
					#  If the image was saved successfully, then
					#  rewrite the links in the post
					#
					if ($img_saved) {
						$wpdb->query("UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '$img_url', '$local_img_url') WHERE ID = '$postid';");
						$img_processed[$img_url] = true;
						if ($opts['show_progress']) {
							echo "<li>ID: $postid, Cached: $img_url =&gt;<br>&nbsp;&nbsp;&nbsp;&nbsp;$local_img_url</li>";
							flush();
						}
					} else {
						#
						#  If we could not read/write the image properly,
						#  then delete file and issue error.
						#
						unlink($pathname);
						if ($opts['show_progress']) {
							echo "<li>ERROR: ID $postid, unable to cache image '".$img_url."'</li>";
							flush();
						}
					}
				} else {
					if ($opts['show_progress']) {
						echo "<li>ERROR: ID $postid, unable to open '".$pathname."' for writing</li>";
						flush();
					}
				}
			}
		}
	}
}

#
#  This function is called after a post is saved and
#  will automatically store local cache images for
#  all the <img src=...> links in the post.
#
function hlic_save_post($postid) {
	global $wpdb;

	if (function_exists('curl_exec')) {
		$opts['urlmethod'] = "curl";
	} else {
		$opts['urlmethod'] = "allow_url_fopen";
	}

	hlic_cache_img($postid, $opts);

	return $postid;
}


#
#  Return relative path to upload directory
#
function hlic_upload_dir() {
	#
	#  make sure abspath has trailing slash
	#
	$abspath = ABSPATH;
	if (!preg_match('/\/$/', $abspath)) {
		$abspath = $abspath . '/';
	}

	#
	#  For backwards-compatibility use old directory if it exists.
	#
	#  On new installations, use the 'upload_path' setting (from
	#  'Miscellaneous Settings' page).  This directory will not
	#  be deleted if the plugin is removed.
	#
	$backwards_compatible = false;

	if ($backwards_compatible) {
		$old_hlic_upload_dir = "wp-content/plugins/hot-linked-image-cacher/upload";
		$upload_dir = $old_hlic_upload_dir;
		$abs_upload_dir = $abspath.$old_hlic_upload_dir;
	}

	if (!$backward_compatible || !$old_hlic_upload_dir || !$is_dir($abs_upload_dir)) {
		$upload_dir = get_option('upload_path').'/HLIC';
	}

	#
	#  Remove any abspath from upload_dir
	#
	$pattern = '/^'.preg_quote($abspath, '/').'/';
	$upload_dir = preg_replace($pattern, '', $upload_dir);
	return $upload_dir;
}

#
#  Return absolute path to upload directory
#
function hlic_abs_upload_dir() {
	#
	#  make sure abspath has trailing slash
	#
	$abspath = ABSPATH;
	if (!preg_match('/\/$/', $abspath)) {
		$abspath = $abspath . '/';
	}

	$abs_upload_dir = $abspath.hlic_upload_dir();
	return $abs_upload_dir;
}

add_action('admin_menu', 'hlic_mm_ci_add_pages');
add_action('save_post', 'hlic_save_post');

?>
