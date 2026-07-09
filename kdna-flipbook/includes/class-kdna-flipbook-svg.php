<?php
/**
 * Safe SVG uploads for KDNA PDF Flipbook.
 *
 * Lets users who can edit entries upload SVG icons through the media library,
 * and sanitises each SVG on upload to strip scripts, event handlers, external
 * references and other active content. SVGs are still powerful files, so uploads
 * are limited to trusted editors and every file is cleaned before it lands.
 *
 * @package Kdna_Flipbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SVG upload handler.
 */
class Kdna_Flipbook_Svg {

	/**
	 * Constructor. Registers the upload hooks.
	 */
	public function __construct() {
		add_filter( 'upload_mimes', array( $this, 'allow_svg_mime' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_svg_filetype' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_upload' ) );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'svg_media_response' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'svg_admin_css' ) );
	}

	/**
	 * Can the current user upload SVG files.
	 *
	 * Allowed for anyone who can edit entries and upload files.
	 *
	 * @return bool
	 */
	protected function user_can_upload_svg() {
		return current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' );
	}

	/**
	 * Allow the SVG mime type for permitted users.
	 *
	 * @param array $mimes Allowed mime types.
	 * @return array
	 */
	public function allow_svg_mime( $mimes ) {
		if ( $this->user_can_upload_svg() ) {
			$mimes['svg'] = 'image/svg+xml';
		}

		return $mimes;
	}

	/**
	 * Help WordPress recognise SVG files by extension.
	 *
	 * @param array  $data     File data (ext, type, proper_filename).
	 * @param string $file     Full path to the file.
	 * @param string $filename The name of the file.
	 * @param array  $mimes    Allowed mimes.
	 * @return array
	 */
	public function fix_svg_filetype( $data, $file, $filename, $mimes ) {
		if ( ! $this->user_can_upload_svg() ) {
			return $data;
		}

		$ext = isset( $data['ext'] ) ? $data['ext'] : '';
		if ( empty( $ext ) ) {
			$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		}

		if ( 'svg' === $ext ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}

		return $data;
	}

	/**
	 * Sanitise an SVG as it is uploaded.
	 *
	 * @param array $file Upload data from $_FILES.
	 * @return array
	 */
	public function sanitize_upload( $file ) {
		$name = isset( $file['name'] ) ? $file['name'] : '';
		$type = isset( $file['type'] ) ? $file['type'] : '';

		$is_svg = 'image/svg+xml' === $type || 'svg' === strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! $is_svg ) {
			return $file;
		}

		if ( ! $this->user_can_upload_svg() ) {
			$file['error'] = __( 'You are not allowed to upload SVG files.', 'kdna-flipbook' );
			return $file;
		}

		if ( empty( $file['tmp_name'] ) || ! $this->sanitize_svg_file( $file['tmp_name'] ) ) {
			$file['error'] = __( 'This SVG could not be cleaned safely, so it was not uploaded.', 'kdna-flipbook' );
		}

		return $file;
	}

	/**
	 * Sanitise an SVG file in place.
	 *
	 * @param string $path File path.
	 * @return bool True on success.
	 */
	protected function sanitize_svg_file( $path ) {
		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content || '' === trim( $content ) ) {
			return false;
		}

		$clean = $this->sanitize_svg_string( $content );
		if ( null === $clean ) {
			return false;
		}

		return false !== file_put_contents( $path, $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Allowed SVG elements. Anything else is removed.
	 *
	 * @var string[]
	 */
	protected $allowed_tags = array(
		'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
		'text', 'tspan', 'textpath', 'tref', 'defs', 'lineargradient', 'radialgradient',
		'stop', 'clippath', 'mask', 'use', 'title', 'desc', 'symbol', 'marker', 'pattern',
		'filter', 'fegaussianblur', 'feoffset', 'feblend', 'femerge', 'femergenode',
		'fecolormatrix', 'metadata', 'switch',
	);

	/**
	 * Sanitise an SVG string, returning cleaned markup or null on failure.
	 *
	 * @param string $svg SVG markup.
	 * @return string|null
	 */
	public function sanitize_svg_string( $svg ) {
		$svg = preg_replace( '/^\xEF\xBB\xBF/', '', $svg );

		// Reject anything using entities, a common route for injection.
		if ( preg_match( '/<!ENTITY/i', $svg ) || preg_match( '/<!DOCTYPE/i', $svg ) ) {
			return null;
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return null;
		}

		$previous = libxml_use_internal_errors( true );

		// Guard against external entity loading on older PHP.
		if ( \PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			$loader = libxml_disable_entity_loader( true );
		}

		$dom    = new DOMDocument();
		$loaded = $dom->loadXML( $svg, LIBXML_NONET );

		if ( \PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) && isset( $loader ) ) {
			libxml_disable_entity_loader( $loader );
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->nodeName ) ) {
			return null;
		}

		$this->scrub_node( $dom->documentElement );

		$out = $dom->saveXML( $dom->documentElement );
		if ( false === $out ) {
			return null;
		}

		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $out;
	}

	/**
	 * Recursively remove disallowed elements, comments and unsafe attributes.
	 *
	 * @param DOMNode $node Node to scrub.
	 */
	protected function scrub_node( $node ) {
		$children = iterator_to_array( $node->childNodes );

		foreach ( $children as $child ) {
			if ( XML_COMMENT_NODE === $child->nodeType || XML_PI_NODE === $child->nodeType ) {
				$node->removeChild( $child );
				continue;
			}

			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}

			if ( ! in_array( strtolower( $child->nodeName ), $this->allowed_tags, true ) ) {
				$node->removeChild( $child );
				continue;
			}

			if ( $child->hasAttributes() ) {
				foreach ( iterator_to_array( $child->attributes ) as $attr ) {
					$this->scrub_attribute( $child, $attr );
				}
			}

			$this->scrub_node( $child );
		}
	}

	/**
	 * Remove an attribute if it is unsafe.
	 *
	 * @param DOMElement $element Element.
	 * @param DOMAttr    $attr    Attribute.
	 */
	protected function scrub_attribute( $element, $attr ) {
		$name  = strtolower( $attr->nodeName );
		$value = (string) $attr->nodeValue;

		// Event handlers.
		if ( 0 === strpos( $name, 'on' ) ) {
			$element->removeAttribute( $attr->nodeName );
			return;
		}

		// Links: allow internal fragment references only.
		if ( 'href' === $name || 'xlink:href' === $name ) {
			$trimmed = trim( $value );
			if ( '' !== $trimmed && 0 !== strpos( $trimmed, '#' ) ) {
				$element->removeAttribute( $attr->nodeName );
				return;
			}
		}

		// Inline styles with active content.
		if ( 'style' === $name && preg_match( '/expression\s*\(|javascript:|behaviour:|behavior:|@import/i', $value ) ) {
			$element->removeAttribute( $attr->nodeName );
			return;
		}

		// Any attribute value that smuggles a script protocol.
		if ( preg_match( '/javascript:/i', $value ) ) {
			$element->removeAttribute( $attr->nodeName );
		}
	}

	/**
	 * Give SVG attachments a usable response in the media modal.
	 *
	 * @param array   $response   Attachment response.
	 * @param WP_Post $attachment Attachment post.
	 * @return array
	 */
	public function svg_media_response( $response, $attachment ) {
		if ( 'image/svg+xml' !== get_post_mime_type( $attachment ) ) {
			return $response;
		}

		$response['icon'] = $response['url'];

		return $response;
	}

	/**
	 * Small admin CSS so SVG thumbnails show at a sensible size.
	 */
	public function svg_admin_css() {
		echo '<style>.attachment .thumbnail img[src$=".svg"],.media-icon img[src$=".svg"],.kdna-flipbook-icon-preview img[src$=".svg"]{width:100%;height:auto;}</style>';
	}
}
