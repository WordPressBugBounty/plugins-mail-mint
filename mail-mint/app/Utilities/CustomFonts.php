<?php

namespace Mint\Utilities;

use MRM\Common\MrmCommon;

if (!defined('ABSPATH')) exit;


class CustomFonts{
    const FONT_CHUNK_SIZE = 25;
    const FONTS = [
        'Abril FatFace',
        'Alegreya',
        'Alegreya Sans',
        'Amatic SC',
        'Anton',
        'Anonymous Pro',
        'Architects Daughter',
        'Archivo',
        'Archivo Narrow',
        'Arimo',
        'Arvo',
        'Asap',
        'Barlow',
        'BioRhyme',
        'Bonbon',
        'Cabin',
        'Cairo',
        'Catamaran',
        'Cardo',
        'Chivo',
        'Concert One',
        'Cormorant',
        'Crimson Text',
        'Della Respira',
        'DM Sans',
        'Eczar',
        'Exo 2',
        'Fira Sans',
        'Fjalla One',
        'Frank Ruhl Libre',
        'Great Vibes',
        'Gilda Display',
        'Heebo',
        'IBM Plex',
        'Inconsolata',
        'Indie Flower',
        'Inknut Antiqua',
        'Inter',
        'Josefin Sans',
        'Jeanne Moderno',
        'Karla',
        'Lato',
        'Lora',
        'Libre Baskerville',
        'Libre Franklin',
        'Montserrat',
        'Marcellus',
        'Merriweather',
        'Merriweather Sans',
        'Nanum Gothic Coding',
        'Neuton',
        'Notable',
        'Noticia Text',
        'Nothing You Could Do',
        'Noto Sans',
        'Noto Sans Georgian',
        'Nunito',
        'Open Sans',
        'Old Standard TT',
        'Oxygen',
        'Pacifico',
        'Poppins',
        'Proza Libre',
        'Playfair Display',
        'PT Sans',
        'PT Serif',
        'Quicksand',
        'Rakkas',
        'Raleway',
        'Reenie Beanie',
        'Recursive Sans',
        'Roboto',
        'Roboto Slab',
        'Ropa Sans',
        'Rubik',
        'Source Code Roman',
        'Source Sans',
        'Syncopate',
        'Shadows Into Light',
        'Space Mono',
        'Spectral',
        'Sue Ellen Francisco',
        'Titillium Web',
        'Tiro Bangla',
        'Ubuntu',
        'Varela',
        'Vollkorn',
        'Work Sans',
        'Yatra One',
    ];

    public function display_custom_fonts(): bool
    {
        $default             = MrmCommon::default_advanced_settings();
        $settings            = get_option('_mint_advanced_settings', $default);
        $load_3rd_party_libs = isset( $settings['load_3rd_party_libraries'] ) ? $settings['load_3rd_party_libraries'] : 'no';

        $display = apply_filters( 'mailmint_display_custom_fonts',  'yes' === $load_3rd_party_libs );
        return (bool)$display;
    }

    public function enqueue_style(){
        if (!$this->display_custom_fonts()) {
            return;
        }

        // Due to a conflict with the WooCommerce Payments plugin, we need to load custom fonts in more requests.
        // When we load all custom fonts in one request, a form from WC Payments isn't displayed correctly.
        // It looks that the larger file size overloads the Stripe SDK.
        foreach (array_chunk(self::FONTS, self::FONT_CHUNK_SIZE) as $key => $fonts) {
            wp_enqueue_style('mailmint_custom_fonts_' . $key, $this->generate_link($fonts));
        }
    }

    public function generate_html_custom_font_link(): string
    {
        if (!$this->display_custom_fonts()) {
            return '';
        }

        $output = '';
        foreach (array_chunk(self::FONTS, self::FONT_CHUNK_SIZE) as $key => $fonts) {
            $output .= sprintf('<link href="%s" rel="stylesheet">', $this->generate_link($fonts));
        }
        return $output;
    }

    private function generate_link(array $fonts): string
    {
        $fonts = array_map(function ($fontName) {
            return urlencode($fontName) . ':400,400i,700,700i';
        }, $fonts);
        $fonts = implode('|', $fonts);
        return 'https://fonts.googleapis.com/css?family=' . $fonts;
    }
}
