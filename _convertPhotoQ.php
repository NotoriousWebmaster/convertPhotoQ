<?php
/*
	_convertPhotoQ.php - Converts PhotoQ posts to regular WordPress posts
	Author - A. Alfred Ayache
	Developed for - DaveWilsonPhotography.com
	Date - 2014 08 23
*/

// remove time limit so script won't run out of time
set_time_limit(0);

// load WP facilities
require_once('wp-load.php');

// uncomment this code block if you don't have the aaLogWP plugin installed.
/*
class NoLog {

	function setCookie() {}
	function logdbg($a) {}
	function logrow($a, $b) {}
	function logRequest() {}
}
$oLog = new NoLog();
*/

$oLog->logRequest();

// thumbnail size registrations
add_image_size('dw_900', 900); // 900 wide, proportionally resized (ie: soft crop)
add_image_size('dw_thumb', 80); // 80 wide, proportionally resized (ie: soft crop)
add_image_size('dw_1280', 1280);
add_image_size('dw_1500', 1500);



?>
<h1>Convert PhotoQ Posts</h1>

<form method="GET">
	<input type="submit" name="submit" value="Convert!" />
</form>

<?php
if (!isset($_REQUEST['submit'])) {
	exit;
}

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

// fetch all PhotoQ posts, 10 at a time
$args = array(
	'posts_per_page'   => 10,
	'offset'           => 0,
	'category'         => '',
	'orderby'          => 'post_date',
	'order'            => 'DESC',
	'include'          => '',
	'exclude'          => '',
	'meta_key'         => '',
	'meta_value'       => '',
	'post_type'        => 'post',
	'post_mime_type'   => '',
	'post_parent'      => '',
	'post_status'      => 'publish',
	'suppress_filters' => true );

while ($aPosts = get_posts($args)) {
	foreach ($aPosts as $k => $aPost) {
		$oLog->logdbg('==========: ' . $k);
		$oLog->logrow("Post {$k}:\n", $aPost);

		// skip if it doesn't have an img tag at the start of the content
		if (substr($aPost->post_content, 0, 4) != '<img') {
			$oLog->logdbg('--- not img: skipping ' . $k);
			continue;
		}

		// get the src image URL
		$content = $aPost->post_content;
		$matches = array();
		$matchCount = preg_match('/src="(.*?)"/', $content, $matches);
		$oLog->logrow('matches', $matches);

		// get PhotoQ description from post_meta
        $photoQDescr = get_post_meta($aPost->ID, 'photoQDescr', true);
        $oLog->logdbg("description:\n{$photoQDescr}");

		// upload image, associating to post
        $sideResult = media_sideload_image($matches[1], $aPost->ID);
        $oLog->logrow('sideResult', $sideResult);

		// replace post content with PhotoQ description
        $sql = "UPDATE wp_posts SET post_content = %s WHERE ID = %d";
        $sqlPrep = $wpdb->prepare($sql, $photoQDescr, $aPost->ID);
        $oLog->logdbg("updating wp_post with PhotoQ description:\n{$sqlPrep}");
        $dbres = $wpdb->query($sqlPrep);
        $oLog->logrow('update result', $dbres);

        // get childrens
        $childArgs = array(
			'post_parent' => $aPost->ID,
			'post_type'   => 'attachment', 
			'posts_per_page' => -1,
			'post_mime_type' => 'image' 
		);
        $attached = get_children($childArgs);
        $oLog->logrow('attached posts', $attached);

        // add featured image
        foreach ($attached as $attachment_id => $attachment) {
        	set_post_thumbnail($aPost->ID, $attachment_id);
        	break;
        }
        
		// done with this post. Get the next one.
	}

	// get the next set of posts
	$args['offset'] += $args['posts_per_page'];

	/*
	// limit the number of posts processed while we're testing
	if ($args['offset'] >= 9) {
		break;
	}
	*/
}
