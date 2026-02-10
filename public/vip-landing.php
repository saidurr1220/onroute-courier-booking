<?php
/**
 * VIP Landing Page Template
 * 
 * This template displays the Elite White Glove VIP Landing Page
 * with the integrated secure consultation form.
 * 
 * @template_name Elite White Glove VIP Landing Page
 * @package OnRoute_Courier_Booking
 */

// Remove all default WordPress hooks
remove_all_actions('wp_head');
remove_all_actions('wp_footer');
remove_all_filters('the_content');

// Re-add essential hooks we need
add_action('wp_head', 'wp_print_styles', 8);
add_action('wp_head', 'wp_print_head_scripts', 9);
add_action('wp_footer', 'wp_print_footer_scripts', 20);

// Display the VIP Landing Page
echo do_shortcode('[vip_landing_page]');
?>
