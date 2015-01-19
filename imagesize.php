<?php 

function filemtime_remote( $uri ) {
	$uri = parse_url( $uri );
	$uri['port'] = isset( $uri['port'] ) ? $uri['port'] : 80;
	$handle = @fsockopen( $uri['host'], $uri['port'] );
	if( ! $handle ) return 0;
	fputs( $handle, "HEAD $uri[path] HTTP/1.1\r\nHost: $uri[host]\r\n\r\n" );
	$result = 0;
	while( ! feof( $handle ) ) {
		$line = fgets( $handle, 1024 );
		if( ! trim( $line ) ) break;
		$col = strpos( $line, ':' );
		if( $col !== false ) {
			$header = trim( substr( $line, 0, $col ) );
			$value = trim( substr( $line, $col + 1 ) );
			if( strtolower( $header ) == 'last-modified' ) {
				$result = strtotime( $value );
				break;
			}
		}
	}
	fclose( $handle );
	return $result;
}
// $b sets a max dimension for the larger of either width or height.  $w is max width. $h is max height.
function imageResize($image, $b = null, $h = null, $w = null, $prefix = "{default prefix-,}", $suffix = "{-suffix1.*,-suffix2.*,.*}"){

$GENERATE_CACHE = true;	// if true, generates caches of the thumbnailed images
$CACHE_PATH = $_SERVER['DOCUMENT_ROOT'] . '/tmp/imagethumbs/';	// the full path where thumbnails should be saved (if $GENERATE_CACHE is enabled)
$BROKEN_IMAGE_PATH = 'no-image.png';	// the full path to the image to be shown when a non-valid image is required or found 
$BASE_HREF = $_SERVER['DOCUMENT_ROOT'] . '/resources/images/';	// the path string to prepend to every image requested (except external resources, if they are allowed) 
$ALLOW_HTTP_IMAGES = false;	// allow requests of http:// or ftp:// resources? Make sure that in php.ini "allow_url_fopen" is set to "true" 

filemtime_remote( $uri );

// if not a static path to an image find image in default path
if ( ! eregi( "^http://", $image ) && ! eregi( "^ftp://", $image ) ) {
$filename = glob($BASE_HREF . $prefix . "{" . $image . "}" . $suffix,GLOB_BRACE);
    $image = $filename[0];
// if image cannot be located default to "no-image" file
if(empty($filename[0])){$image = $BASE_HREF . $BROKEN_IMAGE_PATH;}
}

$src_size = getimagesize( $image );
if ( isset( $w ) && ereg( "^[0-9]+$", $w ) ) $MAX_WIDTH = $w;
else $MAX_WIDTH = $src_size[0];
if ( isset( $h ) && ereg( "^[0-9]+$", $h ) ) $MAX_HEIGHT = $h;
else $MAX_HEIGHT = $src_size[1];
if ( isset( $b ) && ereg( "^[0-9]+$", $b ) ) $MAX_BOTH = $b;

if($src_size[1] > $src_size[0] && isset( $b ) && ereg( "^[0-9]+$", $b )){
$MAX_HEIGHT = $MAX_BOTH;
$MAX_WIDTH = $src_size[0];
}
elseif(isset( $b ) && ereg( "^[0-9]+$", $b )){
$MAX_HEIGHT = $src_size[1];
$MAX_WIDTH = $MAX_BOTH;
}

if ( ! $ALLOW_HTTP_IMAGES && ( eregi( "^http://", $image ) || eregi( "^ftp://", $image ) ) ) {
	$data = readfile( $BROKEN_IMAGE_PATH );
	$dest = imagecreatefromstring( $data );
	header( 'Content-type: image/jpeg' );
	imagejpeg( $dest );
	ob_end_flush();
	exit();
}

// for creating md5 thumbnail file that will update when file is changed
$full_url = str_replace( 'http://', '', $image );
$full_url = filemtime_remote( $image ) . '_' . $MAX_WIDTH . '_' . $MAX_HEIGHT . '_' . $full_url;

$ext = strtolower( substr( $image, strrpos( $image, '.' ) + 1 ) );

$thumb_file = md5( $full_url ) . '_' . basename( $image );
$png_thumb_file = str_replace( '.' . $ext, '.png', $thumb_file );
$gif_thumb_file = str_replace( '.' . $ext, '.gif', $thumb_file );
$jpg_thumb_file = str_replace( '.' . $ext, '.jpg', $thumb_file );



if ( ( ! file_exists( $CACHE_PATH . $png_thumb_file ) &&
	   ! file_exists( $CACHE_PATH . $gif_thumb_file ) &&
	   ! file_exists( $CACHE_PATH . $jpg_thumb_file ) ) ) {

	// resize the image (if needed)
	if ( $src_size[0] > $MAX_WIDTH && $src_size[1] > $MAX_HEIGHT ) {
		if ( $src_size[0] > $src_size[1] ) {
			$dest_width = $MAX_WIDTH;
			$dest_height = ( $src_size[1] * $MAX_WIDTH ) / $src_size[0];
		}
		else {
			$dest_width = ( $src_size[0] * $MAX_HEIGHT ) / $src_size[1];
			$dest_height = $MAX_HEIGHT;
		}
	}
	else if ( $src_size[0] > $MAX_WIDTH ) {
		$dest_width = $MAX_WIDTH;
		$dest_height = ( $src_size[1] * $MAX_WIDTH ) / $src_size[0];
	}
	else if ( $src_size[1] > $MAX_HEIGHT ) {
		$dest_width = ( $src_size[0] * $MAX_HEIGHT ) / $src_size[1];
		$dest_height = $MAX_HEIGHT;
	}
	else {
		$dest_width = $src_size[0];
		$dest_height = $src_size[1];
	}

// force resizing in both dimensions
	if ( $w != null &&  $h != null ) {
		$dest_width = $MAX_WIDTH;
		$dest_height = $MAX_HEIGHT;
	}

	if ( extension_loaded( 'gd' ) ) {

		// check the source file format
		if ( $ext == 'jpg' || $ext == 'jpeg' ){ $src = imagecreatefromjpeg( $image ) or $failed = true;}
		elseif ( $ext == 'gif' ){ $src = imagecreatefromgif( $image ) or $failed = true;}
		elseif ( $ext == 'png' ){ $src = imagecreatefrompng( $image ) or $failed = true;}
		else {$failed = true;}

		if( $failed != true ) {
			// create and output the destination image 
			$dest = imagecreatetruecolor( $dest_width, $dest_height ) or die( 'Cannot initialize new GD image stream' );
imagecolortransparent($dest , imagecolorallocatealpha($dest , 0, 0, 0, 127));
imagealphablending($dest , false); 
imagesavealpha($dest , true);
			imagecopyresampled( $dest, $src, 0, 0, 0, 0, $dest_width, $dest_height, $src_size[0], $src_size[1] );

			if ( imagetypes() & IMG_PNG ) {
				if ( $GENERATE_CACHE ) imagepng( $dest , $CACHE_PATH . $png_thumb_file );
				return "/tmp/imagethumbs/" . $png_thumb_file;
			}
			elseif ( imagetypes() & IMG_JPG ) {
				if ( $GENERATE_CACHE ) imagejpeg( $dest , $CACHE_PATH . $jpg_thumb_file );
				return "/tmp/imagethumbs/" . $jpg_thumb_file;
			}
			else if ( imagetypes() & IMG_GIF ) {
				if ( $GENERATE_CACHE ) imagegif( $dest , $CACHE_PATH . $gif_thumb_file );
				return "/tmp/imagethumbs/" . $gif_thumb_file;
			}
			else {
				$data = readfile( $BROKEN_IMAGE_PATH );
				$dest = imagecreatefromstring( $data );
				return $dest;
			}
		}
		else {
			$data = readfile( $BROKEN_IMAGE_PATH );
			$dest = imagecreatefromstring( $data );
			return $dest;
		}
	}
	else print 'GD-library support is not available';
}
else {
	if ( file_exists( $CACHE_PATH . $png_thumb_file ) ) {
		touch( $CACHE_PATH . $png_thumb_file );
				return "/tmp/imagethumbs/" . $png_thumb_file;
	}
	else if ( file_exists( $CACHE_PATH . $jpg_thumb_file ) ) {
		touch( $CACHE_PATH . $jpg_thumb_file );
				return "/tmp/imagethumbs/" . $jpg_thumb_file;
	}
	else if ( file_exists( $CACHE_PATH . $gif_thumb_file ) ) {
		touch( $CACHE_PATH . $gif_thumb_file );
				return "/tmp/imagethumbs/" . $gif_thumb_file;
	}
	else {
		return $BROKEN_IMAGE_PATH;
	}
}
}
?>
