<?php
/**
 * Plugin Name:       CC Devs
 * Description:       Send copies of admin emails to a list of developers
 * Version:           1.0.4
 * Author:            John Hawkins & Todd Huish
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ccdevs
 *
 * @package CC Devs
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Set some paths.
define( 'CCD_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Setup CCDevs settings on General settings page
 */
function ccd_add_settings_section() {
	add_settings_section(
		'ccd_settings_section',
		'CC Devs',
		'ccd_section_options_callback',
		'general'
	);

	add_settings_field(
		'ccdev_list',
		'Dev Emails',
		'ccdev_list_callback',
		'general',
		'ccd_settings_section',
		array(
			'ccdev_list',
		)
	);

	register_setting( 'general', 'ccdev_list', 'esc_attr' );
}

add_action( 'admin_init', 'ccd_add_settings_section' );

/**
 * Displays a message in our section on the general settings page
 */
function ccd_section_options_callback() {
	echo '<p>' . __( 'Add a comma separated list of email addresses to receive copies of emails sent to the site admin.', 'ccdevs' ) . '</p>';
}

/**
 * Displays the input field in our section on the general settings page
 *
 * @param array $args Arguments passed to the callback.
 */
function ccdev_list_callback( $args ) {
	$option = get_option( $args[0] );
	echo '<input type="text" id="' . esc_attr( $args[0] ) . '" name="' . esc_attr( $args[0] ) . '" value="' . esc_attr( $option ) . '" />';
}

/**
 * Filters emails, if sending to admins, also CC's developers
 *
 * @param array $args Arguments passed to wp_mail.
 */
function ccd_wp_mail_filter( $args ) {

	// Get Admin email.
	$admin_email = get_site_option( 'admin_email' );

	// If going to admin, also send to devs.
	if ( $admin_email === $args['to'] ) {
		// Grab list of dev emails.
		$list_of_devs = explode( ',', get_option( 'ccdev_list' ) );
		$list_of_devs = array_map( 'trim', $list_of_devs );

		// Loop through each dev and send email.
		foreach ( $list_of_devs as $dev_email ) {
			// Create hash & set transient.
			$timehash = md5( gmdate( 'U' ) . $dev_email );
			set_transient( 'ccdevs_' . $timehash, $dev_email, 3 * DAY_IN_SECONDS );

			// Build unsubscribe text link.
			$unsub_text  = "\n\n";
			$unsub_text .= __( 'To unsubscribe from these emails, ', 'ccdevs' );
			$unsub_text .= '<a href="' . get_bloginfo( 'wpurl' ) . '/?ccdt=' . esc_attr( $timehash ) . '">';
			$unsub_text .= __( 'Click Here', 'ccdevs' );
			$unsub_text .= '</a>';

			// Setup our unique emails.
			$to          = $dev_email;
			$subject     = $args['subject'];
			$message     = $args['message'] . $unsub_text;
			$headers     = $args['headers'];
			$attachments = $args['attachments'];

			// Send our unique emails.
			wp_mail( $to, $subject, $message, $headers, $attachments );
		}
	}

	// Return unedited args so all other emails go out normally.
	return $args;
}

add_filter( 'wp_mail', 'ccd_wp_mail_filter' );

/**
 * Unsubscribe devs from receiving admin emails.
 */
function ccd_unsubscribe_devs() {
	if ( isset( $_GET['ccdt'] ) ) {
		$transient_name = 'ccdevs_' . $_GET['ccdt'];

		// See if a transient exists to match the inbound link.
		if ( get_transient( $transient_name ) ) {
			$dev_email    = get_transient( $transient_name );
			$list_of_devs = explode( ',', get_option( 'ccdev_list' ) );
			$list_of_devs = array_map( 'trim', $list_of_devs );

			// See if email is in our list of devs.
			if ( is_int( array_search( $dev_email, $list_of_devs, true ) ) ) {
				// Remove email from array.
				$key = array_search( $dev_email, $list_of_devs, true );
				unset( $list_of_devs[ $key ] );
				// Push updated list back to options table.
				update_option( 'ccdev_list', implode( ',', $list_of_devs ) );
			}
		}
	}
}

add_action( 'init', 'ccd_unsubscribe_devs' );
