<?php


class Wds_Webp_Generate {
	static private $instance = null;
	private function __construct() {

	}

	static public function getInstance() {
		if(self::$instance == null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Generate picture tag
	 * @param string $alt
	 * @param false $file_id
	 * @param false $file_url
	 * @param string $img_size
	 *
	 * @return false|string
	 */
	public function process_picture(string $alt, bool $file_id, $file_url = false, $img_size = 'large') {
		return $this->process($file_id, $file_url, $img_size, 'picture', $alt);
	}

	/**
	 * Generate image tag with srcset attributes
	 * @param string $alt
	 * @param false $file_id
	 * @param false $file_url
	 * @param string $img_size
	 *
	 * @return false|string
	 */
	public function process_webp(string $alt, bool $file_id, $file_url = false, $img_size = 'large') {
		return $this->process($file_id, $file_url, $img_size, 'img', $alt);
	}

	/**
	 * @param false $file_id
	 * @param string $file_url
	 * @param string $img_size
	 * @param string $type
	 * @param string $alt
	 *
	 * @return false|string
	 */
	public function process(  bool $file_id, string $file_url, string $img_size , string $type, string $alt ) {
		if(!$file_id && $file_url) {
			$file_id = attachment_url_to_postid( $file_url );
		}

		if(!$file_id) return '';

		$webp = $this->create_webp($file_id);

		if ($webp) {
			return $this->html_builder_webp($webp, $file_id, $img_size, $type, $alt);
		} else {
			return $this->html_builder_fallback($file_id, $img_size, $alt);
		}
	}

	/**
	 * @param $file_id
	 *
	 * @return false|string
	 */
	public function create_webp($file_id) {
		$upload_dir    = wp_get_upload_dir();
		$image_basename = wp_basename(get_attached_file($file_id) );
		return $this->generate_image_src($upload_dir['path'].'/'.$image_basename );
	}


	/**
	 * @param $webp
	 * @param $file_id
	 * @param $img_size
	 * @param $type
	 * @param $alt
	 *
	 * @return string
	 */
	public function html_builder_webp($webp, $file_id, $img_size, $type, $alt): string {
		if($type == 'picture') {
			return '<picture>
					    <source srcset="'. $this->get_attachment_image_srcset($file_id, $img_size).'">
					    <img src="'.wp_get_attachment_image_url( $file_id, $img_size ).'" alt="My default image">
					</picture>';
		} else {
			return '<img 
						src="'.$this->abs_path_to_url($webp).'" 
						srcset="'. $this->get_attachment_image_srcset($file_id, $img_size).'" 
						sizes="'.wp_get_attachment_image_sizes( $file_id, $img_size ).'" 
						alt="'. $alt .'" 
					/>';
		}

	}

	/**
	 * @param $file_id
	 * @param $img_size
	 * @param $alt
	 *
	 * @return string
	 */
	public function html_builder_fallback($file_id, $img_size, $alt): string {
		return '<img 
					src="'.wp_get_attachment_image_url( $file_id, $img_size ).'" 
					srcset="'.wp_get_attachment_image_srcset( $file_id, $img_size ).'" 
					sizes="'.wp_get_attachment_image_sizes( $file_id, $img_size ).'" 
					alt="'. $alt .'" 
				/>';
	}

	/**
	 * Generate Webp image format
	 *
	 * Uses either Imagick or imagewebp to generate webp image
	 *
	 * @param string $file Path to image being converted.
	 * @param int $compression_quality Quality ranges from 0 (worst quality, smaller file) to 100 (best quality, biggest file).
	 *
	 * @return false|string Returns path to generated webp image, otherwise returns false.
	 */
	public function generate_image_src($file, $compression_quality = 100) {
		// check if file exists
		if (!file_exists($file)) {
			return false;
		}

		$orig_file_size = filesize($file);

		// If output file already exists return path
		$output_file = $file . '.webp';
		if (file_exists($output_file)) {

			$webp_file_size = filesize($output_file);
			if($webp_file_size > $orig_file_size) return false;

			return $output_file;
		}

		$file_type = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		if (function_exists('imagewebp')) {

			switch ($file_type) {
				case 'jpeg':
				case 'jpg':
					$image = imagecreatefromjpeg($file);
					break;

				case 'png':
					$image = imagecreatefrompng($file);
					imagepalettetotruecolor($image);
					imagealphablending($image, true);
					imagesavealpha($image, true);
					break;

				case 'gif':
					$image = imagecreatefromgif($file);
					break;
				default:
					return false;
			}

			// Save the image
			$result = imagewebp($image, $output_file, $compression_quality);
			if (false === $result) {
				return false;
			}

			// Free up memory
			imagedestroy($image);

			$webp_file_size = filesize($output_file);
			if($webp_file_size > $orig_file_size) return false;

			return $output_file;
		} elseif (class_exists('Imagick')) {
			$image = new Imagick();
			$image->readImage($file);

			if ($file_type === 'png') {
				$image->setImageFormat('webp');
				$image->setImageCompressionQuality($compression_quality);
				$image->setOption('webp:lossless', 'true');
			}

			$image->writeImage($output_file);

			$webp_file_size = filesize($output_file);
			if($webp_file_size > $orig_file_size) return false;
			return $output_file;
		}

		return false;
	}

	/**
	 * @param string $path
	 *
	 * @return string
	 */
	public function abs_path_to_url( $path = '' ) {
		$url = str_replace(
			wp_normalize_path( untrailingslashit( ABSPATH ) ),
			site_url(),
			wp_normalize_path( $path )
		);
		return esc_url_raw( $url );
	}

	function get_attachment_image_srcset( $attachment_id, $size = 'medium', $image_meta = null ) {
		$image = wp_get_attachment_image_src( $attachment_id, $size );

		if ( ! $image ) {
			return false;
		}

		if ( ! is_array( $image_meta ) ) {
			$image_meta = wp_get_attachment_metadata( $attachment_id );
		}

		$image_src  = $image[0];
		$size_array = array(
			absint( $image[1] ),
			absint( $image[2] ),
		);

		return $this->calculate_image_srcset( $size_array, $image_src, $image_meta, $attachment_id );
	}

	function calculate_image_srcset( $size_array, $image_src, $image_meta, $attachment_id = 0 ) {
		/**
		 * Let plugins pre-filter the image meta to be able to fix inconsistencies in the stored data.
		 *
		 * @since 4.5.0
		 *
		 * @param array  $image_meta    The image meta data as returned by 'wp_get_attachment_metadata()'.
		 * @param int[]  $size_array    {
		 *     An array of requested width and height values.
		 *
		 *     @type int $0 The width in pixels.
		 *     @type int $1 The height in pixels.
		 * }
		 * @param string $image_src     The 'src' of the image.
		 * @param int    $attachment_id The image attachment ID or 0 if not supplied.
		 */
		$image_meta = apply_filters( 'wds_calculate_image_srcset', $image_meta, $size_array, $image_src, $attachment_id );

		if ( empty( $image_meta['sizes'] ) || ! isset( $image_meta['file'] ) || strlen( $image_meta['file'] ) < 4 ) {
			return false;
		}

		$image_sizes = $image_meta['sizes'];

		// Get the width and height of the image.
		$image_width  = (int) $size_array[0];
		$image_height = (int) $size_array[1];

		// Bail early if error/no width.
		if ( $image_width < 1 ) {
			return false;
		}

		$image_basename = wp_basename( $image_meta['file'] );

		/*
		 * WordPress flattens animated GIFs into one frame when generating intermediate sizes.
		 * To avoid hiding animation in user content, if src is a full size GIF, a srcset attribute is not generated.
		 * If src is an intermediate size GIF, the full size is excluded from srcset to keep a flattened GIF from becoming animated.
		 */
		if ( ! isset( $image_sizes['thumbnail']['mime-type'] ) || 'image/gif' !== $image_sizes['thumbnail']['mime-type'] ) {
			$image_sizes[] = array(
				'width'  => $image_meta['width'],
				'height' => $image_meta['height'],
				'file'   => $image_basename,
			);
		} elseif ( strpos( $image_src, $image_meta['file'] ) ) {
			return false;
		}

		// Retrieve the uploads sub-directory from the full size image.
		$dirname = _wp_get_attachment_relative_path( $image_meta['file'] );

		if ( $dirname ) {
			$dirname = trailingslashit( $dirname );
		}

		$upload_dir    = wp_get_upload_dir();
		$image_baseurl = trailingslashit( $upload_dir['baseurl'] ) . $dirname;

		/*
		 * If currently on HTTPS, prefer HTTPS URLs when we know they're supported by the domain
		 * (which is to say, when they share the domain name of the current request).
		 */
		if ( is_ssl() && 'https' !== substr( $image_baseurl, 0, 5 ) && parse_url( $image_baseurl, PHP_URL_HOST ) === $_SERVER['HTTP_HOST'] ) {
			$image_baseurl = set_url_scheme( $image_baseurl, 'https' );
		}

		/*
		 * Images that have been edited in WordPress after being uploaded will
		 * contain a unique hash. Look for that hash and use it later to filter
		 * out images that are leftovers from previous versions.
		 */
		$image_edited = preg_match( '/-e[0-9]{13}/', wp_basename( $image_src ), $image_edit_hash );

		/**
		 * Filters the maximum image width to be included in a 'srcset' attribute.
		 *
		 * @since 4.4.0
		 *
		 * @param int   $max_width  The maximum image width to be included in the 'srcset'. Default '2048'.
		 * @param int[] $size_array {
		 *     An array of requested width and height values.
		 *
		 *     @type int $0 The width in pixels.
		 *     @type int $1 The height in pixels.
		 * }
		 */
		$max_srcset_image_width = apply_filters( 'max_srcset_image_width', 2048, $size_array );

		// Array to hold URL candidates.
		$sources = array();

		/**
		 * To make sure the ID matches our image src, we will check to see if any sizes in our attachment
		 * meta match our $image_src. If no matches are found we don't return a srcset to avoid serving
		 * an incorrect image. See #35045.
		 */
		$src_matched = false;

		/*
		 * Loop through available images. Only use images that are resized
		 * versions of the same edit.
		 */
		foreach ( $image_sizes as $image ) {
			$is_src = false;

			// Check if image meta isn't corrupted.
			if ( ! is_array( $image ) ) {
				continue;
			}

			// If the file name is part of the `src`, we've confirmed a match.
			if ( ! $src_matched && false !== strpos( $image_src, $dirname . $image['file'] ) ) {
				$src_matched = true;
				$is_src      = true;
			}

			// Filter out images that are from previous edits.
			if ( $image_edited && ! strpos( $image['file'], $image_edit_hash[0] ) ) {
				continue;
			}

			/*
			 * Filters out images that are wider than '$max_srcset_image_width' unless
			 * that file is in the 'src' attribute.
			 */
			if ( $max_srcset_image_width && $image['width'] > $max_srcset_image_width && ! $is_src ) {
				continue;
			}

			// If the image dimensions are within 1px of the expected size, use it.
			if ( wp_image_matches_ratio( $image_width, $image_height, $image['width'], $image['height'] ) ) {
				// Add the URL, descriptor, and value to the sources array to be returned.
				$wbp_url = $this->generate_image_src($upload_dir['path'].'/'.$image['file']);
				$source = array(
					'url'        => $image_baseurl.basename($wbp_url),
					'descriptor' => 'w',
					'value'      => $image['width'],
				);

				// The 'src' image has to be the first in the 'srcset', because of a bug in iOS8. See #35030.
				if ( $is_src ) {
					$sources = array( $image['width'] => $source ) + $sources;
				} else {
					$sources[ $image['width'] ] = $source;
				}
			}
		}

		/**
		 * Filters an image's 'srcset' sources.
		 *
		 * @since 4.4.0
		 *
		 * @param array  $sources {
		 *     One or more arrays of source data to include in the 'srcset'.
		 *
		 *     @type array $width {
		 *         @type string $url        The URL of an image source.
		 *         @type string $descriptor The descriptor type used in the image candidate string,
		 *                                  either 'w' or 'x'.
		 *         @type int    $value      The source width if paired with a 'w' descriptor, or a
		 *                                  pixel density value if paired with an 'x' descriptor.
		 *     }
		 * }
		 * @param array $size_array     {
		 *     An array of requested width and height values.
		 *
		 *     @type int $0 The width in pixels.
		 *     @type int $1 The height in pixels.
		 * }
		 * @param string $image_src     The 'src' of the image.
		 * @param array  $image_meta    The image meta data as returned by 'wp_get_attachment_metadata()'.
		 * @param int    $attachment_id Image attachment ID or 0.
		 */

		$sources = apply_filters( array($this, 'calculate_image_srcset'), $sources, $size_array, $image_src, $image_meta, $attachment_id );

		// Only return a 'srcset' value if there is more than one source.
		if ( ! $src_matched || ! is_array( $sources ) || count( $sources ) < 2 ) {
			return false;
		}

		$srcset = '';

		foreach ( $sources as $source ) {

			$srcset .= str_replace( ' ', '%20', $source['url'] ) . ' ' . $source['value'] . $source['descriptor'] . ', ';
		}

		return rtrim( $srcset, ', ' );
	}
}

if(!function_exists('wds_webp_generate')) {

	/**
	 * This is just a tiny wrapper function for the class above so that there is no
	 * need to change any code in your own WP themes. Usage is still the same :)
	 *
	 * @param $alt
	 * @param int $file_id
	 * @param bool $file_url
	 * @param string $img_size
	 *
	 * @return false|string
	 */
	function wds_webp_generate( $alt, $file_id = 0, $file_url = false, $img_size = 'large' ) {
		$wds_webp_generate = Wds_Webp_Generate::getInstance();

		return $wds_webp_generate->process_webp($alt, $file_id, $file_url, $img_size);
	}

}

if(!function_exists('wds_picture_generate')) {

	/**
	 * This is just a tiny wrapper function for the class above so that there is no
	 * need to change any code in your own WP themes. Usage is still the same :)
	 *
	 * @param $alt
	 * @param int $file_id
	 * @param bool $file_url
	 * @param string $img_size
	 *
	 * @return false|string
	 */
	function wds_picture_generate( $alt, $file_id = 0, $file_url = false, $img_size = 'large' ) {
		$wds_webp_generate = Wds_Webp_Generate::getInstance();

		return $wds_webp_generate->process_picture($alt, $file_id, $file_url, $img_size);
	}

}