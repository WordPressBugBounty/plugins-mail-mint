<?php
/**
 * Helper class for Mail Mint countdown timer GIF creation
 *
 * @package Mint\MRM\Utilites\Helper
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\Utilites\Helper;

use Exception;

/**
 * Encode animated gifs
 */
class AnimatedGif {

	/**
	 * The built gif image
	 *
	 * @var resource
	 */
	private $image = '';

	/**
	 * The array of images to stack
	 *
	 * @var array
	 */
	private $buffer = array();

	/**
	 * How many times to loop? 0 = infinite
	 *
	 * @var int
	 */
	private $number_of_loops = 0;

	/**
	 * How many times to loop? 0 = infinite
	 *
	 * @var int
	 */
	private $dis = 2;

	/**
	 * Which colour is transparent
	 *
	 * @var int
	 */
	private $transparent_colour = -1;

	/**
	 * Is this the first frame
	 *
	 * @var int
	 */
	private $first_frame = true;

	/**
	 * Encode an animated gif
	 *
	 * @param array $source_images An array of binary source images.
	 * @param array $image_delays The delays associated with the source images.
	 * @param type  $number_of_loops The number of times to loop.
	 * @param int   $transparent_colour_red Background transparent color.
	 * @param int   $transparent_colour_green Background transparent color.
	 * @param int   $transparent_colour_blue Background transparent color.
	 */
	public function __construct( array $source_images, array $image_delays, $number_of_loops, $transparent_colour_red = -1, $transparent_colour_green = -1, $transparent_colour_blue = -1 ) {
		/**
		 * I have no idea what these even do, they appear to do nothing to the image so far.
		 */
		$transparent_colour_red   = 0;
		$transparent_colour_green = 0;
		$transparent_colour_blue  = 0;

		$this->number_of_loops = ( $number_of_loops > -1 ) ? $number_of_loops : 0;
		$this->set_transparent_colour( $transparent_colour_red, $transparent_colour_green, $transparent_colour_blue );
		$this->buffer_images( $source_images );

		$this->add_header();

		$count_buffer = count( $this->buffer );

		for ( $i = 0; $i < $count_buffer; $i++ ) {
			$this->add_frame( $i, $image_delays [ $i ] ); // phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed
		}
	}

	/**
	 * Set the transparent colour
	 *
	 * @param int $red Red transparent color.
	 * @param int $green Green transparent color.
	 * @param int $blue Blue transparent color.
	 */
	private function set_transparent_colour( $red, $green, $blue ) {
		$this->transparent_colour = ( $red > -1 && $green > -1 && $blue > -1 ) ?
				( $red | ( $green << 8 ) | ( $blue << 16 ) ) : -1;
	}

	/**
	 * Buffer the images and check to make sure they are vaild
	 *
	 * @param array $source_images the array of source images.
	 * @throws Exception    $e Throws an exception if the action could not be saved.
	 */
	private function buffer_images( $source_images ) {
		$count_source_images = count( $source_images );
		for ( $i = 0; $i < $count_source_images; $i++ ) {
			$this->buffer [] = $source_images [ $i ];
			if ( substr( $this->buffer [ $i ], 0, 6 ) !== 'GIF87a' && substr( $this->buffer [ $i ], 0, 6 ) !== 'GIF89a' ) {
				throw new Exception( 'Image at position ' . $i . ' is not a gif' );
			}
			for ( $j = ( 13 + 3 * ( 2 << ( ord( $this->buffer [ $i ] [10] ) & 0x07 ) ) ), $k = true; $k; $j++ ) {
				switch ( $this->buffer [ $i ] [ $j ] ) {
					case '!':
						if ( ( substr( $this->buffer [ $i ], ( $j + 3 ), 8 ) ) === 'NETSCAPE' ) {
							throw new Exception( 'You cannot make an animation from an animated gif.' );
						}
						break;
					case ';':
						$k = false;
						break;
				}
			}
		}
	}

	/**
	 * Add the gif header to the image
	 */
	private function add_header() {
		$cmap        = 0;
		$this->image = 'GIF89a';
		if ( ord( $this->buffer [0] [10] ) & 0x80 ) {
			$cmap         = 3 * ( 2 << ( ord( $this->buffer [0] [10] ) & 0x07 ) );
			$this->image .= substr( $this->buffer [0], 6, 7 );
			$this->image .= substr( $this->buffer [0], 13, $cmap );
			$this->image .= "!\377\13NETSCAPE2.0\3\1" . $this->word( $this->number_of_loops ) . "\0";
		}
	}

	/**
	 * Add a frame to the animation
	 *
	 * @param int $frame The frame to be added.
	 * @param int $delay The delay associated with the frame.
	 */
	private function add_frame( $frame, $delay ) {
		$locals_str = 13 + 3 * ( 2 << ( ord( $this->buffer [ $frame ] [10] ) & 0x07 ) );

		$locals_end = strlen( $this->buffer [ $frame ] ) - $locals_str - 1;
		$locals_tmp = substr( $this->buffer [ $frame ], $locals_str, $locals_end );

		$global_len = 2 << ( ord( $this->buffer [0] [10] ) & 0x07 );
		$locals_len = 2 << ( ord( $this->buffer [ $frame ] [10] ) & 0x07 );

		$global_rgb = substr( $this->buffer [0], 13, 3 * ( 2 << ( ord( $this->buffer [0] [10] ) & 0x07 ) ) );
		$locals_rgb = substr( $this->buffer [ $frame ], 13, 3 * ( 2 << ( ord( $this->buffer [ $frame ] [10] ) & 0x07 ) ) );

		$locals_ext = "!\xF9\x04" . chr( ( $this->dis << 2 ) + 0 ) .
				chr( ( $delay >> 0 ) & 0xFF ) . chr( ( $delay >> 8 ) & 0xFF ) . "\x0\x0";

		if ( $this->transparent_colour > -1 && ord( $this->buffer [ $frame ] [10] ) & 0x80 ) {
			for ( $j = 0; $j < ( 2 << ( ord( $this->buffer [ $frame ] [10] ) & 0x07 ) ); $j++ ) { // phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed
				if (
						ord( $locals_rgb [ 3 * $j + 0 ] ) === ( ( $this->transparent_colour >> 16 ) & 0xFF ) &&
						ord( $locals_rgb [ 3 * $j + 1 ] ) === ( ( $this->transparent_colour >> 8 ) & 0xFF ) &&
						ord( $locals_rgb [ 3 * $j + 2 ] ) === ( ( $this->transparent_colour >> 0 ) & 0xFF )
				) {
					$locals_ext = "!\xF9\x04" . chr( ( $this->dis << 2 ) + 1 ) .
							chr( ( $delay >> 0 ) & 0xFF ) . chr( ( $delay >> 8 ) & 0xFF ) . chr( $j ) . "\x0";
					break;
				}
			}
		}
		switch ( $locals_tmp [0] ) {
			case '!':
				$locals_img = substr( $locals_tmp, 8, 10 );
				$locals_tmp = substr( $locals_tmp, 18, strlen( $locals_tmp ) - 18 );
				break;
			case ',':
				$locals_img = substr( $locals_tmp, 0, 10 );
				$locals_tmp = substr( $locals_tmp, 10, strlen( $locals_tmp ) - 10 );
				break;
		}
		if ( ord( $this->buffer [ $frame ] [10] ) & 0x80 && false === $this->first_frame ) {
			if ( $global_len === $locals_len ) {
				if ( $this->block_compare( $global_rgb, $locals_rgb, $global_len ) ) {
					$this->image .= ( $locals_ext . $locals_img . $locals_tmp );
				} else {
					$byte           = ord( $locals_img [9] );
					$byte          |= 0x80;
					$byte          &= 0xF8;
					$byte          |= ( ord( $this->buffer [0] [10] ) & 0x07 );
					$locals_img [9] = chr( $byte );
					$this->image   .= ( $locals_ext . $locals_img . $locals_rgb . $locals_tmp );
				}
			} else {
				$byte           = ord( $locals_img [9] );
				$byte          |= 0x80;
				$byte          &= 0xF8;
				$byte          |= ( ord( $this->buffer [ $frame ] [10] ) & 0x07 );
				$locals_img [9] = chr( $byte );
				$this->image   .= ( $locals_ext . $locals_img . $locals_rgb . $locals_tmp );
			}
		} else {
			$this->image .= ( $locals_ext . $locals_img . $locals_tmp );
		}
		$this->first_frame = false;
	}

	/**
	 * Add the gif footer
	 */
	private function add_footer() {
		$this->image .= ';';
	}

	/**
	 * Compare gif blocks? What is a block?
	 *
	 * @param type $global_block Global block.
	 * @param type $local_block Local block.
	 * @param type $len Block length.
	 * @return type
	 */
	private function block_compare( $global_block, $local_block, $len ) {
		for ( $i = 0; $i < $len; $i++ ) {
			if (
					$global_block [ 3 * $i + 0 ] !== $local_block [ 3 * $i + 0 ] ||
					$global_block [ 3 * $i + 1 ] !== $local_block [ 3 * $i + 1 ] ||
					$global_block [ 3 * $i + 2 ] !== $local_block [ 3 * $i + 2 ]
			) {
				return ( 0 );
			}
		}

		return ( 1 );
	}

	/**
	 * No clue
	 *
	 * @param int $int Word count value.
	 * @return string the char you meant?
	 */
	private function word( $int ) {
		return ( chr( $int & 0xFF ) . chr( ( $int >> 8 ) & 0xFF ) );
	}

	/**
	 * Return the animated gif
	 *
	 * @return type
	 */
	public function get_animation() {
		return $this->image;
	}

	/**
	 * Return the animated gif
	 *
	 * @return void
	 */
	public function display() {
		// late footer add.
		$this->add_footer();
		header( 'Content-type:image/gif' );
		echo $this->image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

}
