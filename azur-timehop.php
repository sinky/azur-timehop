<?php
/*
Plugin Name: Azur Timehop
Plugin URI: https://xxxx.com/sinky/azur-timehop
Version: 1.0
Author: Marco Krage
Author URI: https://my-azur.de
Description: Shows old Posts through shortcut and sends a mail once a month
*/

$azur_timehop_config = array(
	'sections' => array(
		'vor einem Jahr' => '-1 year',
		'vor zwei Jahren' => '-2 year',
		'vor fünf Jahren' => '-5 year',
		'vor zehn Jahren' => '-10 year',
		'vor 20 Jahren' => '-20 year',
		'vor 30 Jahren' => '-30 year'
	),
	'show_empty_sections' => false,
	'excerpt_length' => '20',
	'excerpt_more' => ' ...',
	'date_format' => 'd.m.y',
	'localized_month' => array(1=>"Januar", 2=>"Februar", 3=>"März", 4=>"April", 5=>"Mai", 6=>"Juni", 7=>"Juli", 8=>"August", 9=>"September", 10=>"Oktober", 11=>"November", 12=>"Dezember"),
	'mail_send_active' => false,
	'mail_sender' => "",
	'mail_recipient' => "",
	'mail_subject_prefix' => "", // result: PREFIX - Month
);

$azur_timehop_settings = get_option('azur_timehop_settings') ?: [];
$azur_timehop_config = array_merge($azur_timehop_config, $azur_timehop_settings);

function azur_custom_length_excerpt($word_count_limit, $more) {
    $content = strip_shortcodes(wp_strip_all_tags(get_the_content() , true ));
    return wp_trim_words($content, $word_count_limit, $more);
}

// HTML Output für gefundene Posts erstellen
function timehop_output() {
	global $azur_timehop_config;
	$output = '';
	
	foreach($azur_timehop_config['sections'] as $headline => $section) {

		$posts = timehop_get_posts_from(date("Y-m-1", strtotime($section)), date("Y-m-t", strtotime($section)));

		$html_list_items = '';
		foreach($posts as $post){
			$post = timehop_key_wrap($post, '{', '}');
			$html_list_items .=  str_replace(array_keys($post), $post, '<li><a href="{link}">{title}</a> - {excerpt} ({date})</li>');
		}

		$headline = "<h2>$headline - ".date("Y", strtotime($section))."</h2>";
		if($posts) {
			$output .=  $headline;
			$output .= "<ul>$html_list_items</ul>";
		}elseif($azur_timehop_config['show_empty_sections']){
			$output .= $headline;
			$output .= "<p>Keine Beiträge</p>";
		}
	}
	return $output;
}

// Wrap Array Keys for templating
function timehop_key_wrap($array, $prefix, $suffix) {
	foreach($array as $k=>$v){
		$array[$prefix.$k.$suffix] = $v;
		unset($array[$k]);
	}
  return $array;
}

// Posts auf Grund eines Start/Endpunktes finden
function timehop_get_posts_from($start, $end) {
	global $azur_timehop_config;
	$args = array(
		'date_query' => array(
			array(
				'after'     => $start,
				'before'    => $end,
			),
			'inclusive' => true
		),
		'order' => 'ASC',
		'posts_per_page' => -1
	);

	$query = new WP_Query( $args );

	$posts = array();

	while ( $query->have_posts() ) {
		$query->the_post();

		$post = array(
			"title" => get_the_title(),
			"excerpt" => azur_custom_length_excerpt($azur_timehop_config['excerpt_length'], $azur_timehop_config['excerpt_more']),
			"link" => get_the_permalink(),
			"date" => get_the_date($azur_timehop_config['date_format'])
		);

		array_push($posts, $post);
	}
	
	wp_reset_query();
	
	return $posts;
}

// Shortcode registrieren
function azur_timehop_shortcode() {
	$shortcode_output = timehop_output();
	return $shortcode_output;
}
add_shortcode('azur-timehop', 'azur_timehop_shortcode');


//
// Cron und Mailversand
//

// Monatlichen Zeitintervall für WP-Cron definieren
function timehop_add_intervals($schedules) {
	$schedules['monthly'] = array(
		'interval' => 2635200,
		'display' => __('Once a month')
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'timehop_add_intervals'); 

// Plugin Aktivierung
register_activation_hook(__FILE__, 'timehop_activation');
function timehop_activation() {
	// WP Cron aktivieren
	if( ! wp_next_scheduled ( 'timehop_monthly' ) ) {
		$startTime = new DateTime('now', new DateTimeZone('Europe/Berlin'));
		$startTime->modify('+1 day');
		$startTime->setTime(5, 0, 0);
        wp_schedule_event( $startTime->getTimestamp(), 'daily', 'timehop_monthly');
    }
}

// Plugin Deaktivierung
register_deactivation_hook(__FILE__, 'timehop_deactivation');
function timehop_deactivation() {
	// WP Cron deaktivieren
	wp_clear_scheduled_hook('timehop_monthly');
}

function timehop_monthly(){
 	if ('1' == date('D')) {
		timehop_sendmail();
	}
}

// Aktion zum WP-Cron registrieren
if($azur_timehop_config['mail_send_active']) {
	add_action('timehop_monthly', 'timehop_monthly');
}

// HTML Output per Mail versenden
function timehop_sendmail() {
 	global $azur_timehop_config;
	$mail_message = "<html><body>";
	$mail_message .= timehop_output();
	$mail_message .= "</body></html>";

	$mail_headers = "From: ".$azur_timehop_config['mail_sender']."\r\n";
	$mail_headers .= "MIME-Version: 1.0\r\n";
	$mail_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
	
	$mail_subject = $azur_timehop_config['mail_subject_prefix']." - ".$azur_timehop_config['localized_month'][date("n")];

	$res = wp_mail($azur_timehop_config['mail_recipient'], $mail_subject, $mail_message, $mail_headers);
}


// https://github.com/jeremyHixon/RationalOptionPages
require_once('RationalOptionPages.php');

$azur_timehop_option_pages = array(
	'azur_timehop_settings'	=> array( // Page Slug (is also settings name)
		'page_title' => 'Azur Timehop', // Page Title
		'parent_slug'	=> 'options-general.php', // Parent
		'sections' => array(
			'section-one' => array(
				'title' => 'Options',
				'fields' => array(
					array(
						'id' => 'mail_send_active',
						'title' => 'Send Email',
						'type' => 'checkbox',
						'text' => 'Send Timehop Email once a month',
						'value' => '1'
					),
					array(
						'id' => 'mail_sender',
						'title' => 'Email Sender',
						'type' => 'email',
						'placeholder' => 'you@mail.com'
					),
					array(
						'id' => 'mail_recipient',
						'title' => 'Email Recipient',
						'type' => 'email',
						'placeholder' => 'you@mail.com'
					),
					array(
						'id' => 'mail_subject_prefix',
						'title' => 'Email Subject Prefix',
						'text' => 'Result: PREFIX - Month',
						'placeholder' => 'Timehop'
					),
				),
			),
		),
	),
);

$azur_timehop_option_page = new RationalOptionPages($azur_timehop_option_pages);
