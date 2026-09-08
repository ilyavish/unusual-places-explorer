<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['up_meta'] = array();
$GLOBALS['up_transients'] = array();
$GLOBALS['up_scheduled'] = array();

function add_shortcode() {}
function add_action() {}
function add_filter() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function wp_next_scheduled($hook) { return $GLOBALS['up_scheduled'][$hook] ?? false; }
function wp_schedule_single_event($time, $hook) { $GLOBALS['up_scheduled'][$hook] = $time; return true; }
function get_transient($key) { return $GLOBALS['up_transients'][$key] ?? false; }
function set_transient($key, $value) { $GLOBALS['up_transients'][$key] = $value; return true; }
function delete_transient($key) { unset($GLOBALS['up_transients'][$key]); return true; }
function get_post_meta($post_id, $key = '', $single = false) {
	if ('' === $key) return $GLOBALS['up_meta'][$post_id] ?? array();
	return $GLOBALS['up_meta'][$post_id][$key] ?? '';
}
function update_post_meta($post_id, $key, $value) { $GLOBALS['up_meta'][$post_id][$key] = $value; return true; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['up_meta'][$post_id][$key]); return true; }
function wp_is_post_revision() { return false; }
function wp_is_post_autosave() { return false; }
function wp_verify_nonce() { return true; }
function current_user_can() { return true; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_rand() { return 1234; }
function rest_url($path) { return 'https://example.test/wp-json/' . $path; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return filter_var((string) $value, FILTER_SANITIZE_URL); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }

require dirname(__DIR__) . '/unusual-places-explorer/unusual-places-explorer.php';

$plugin = UP_Strange_Place_Picker::instance();
$tests = 0;

function invoke_private($object, $method, ...$args) {
	$reflection = new ReflectionMethod($object, $method);
	return $reflection->invoke($object, ...$args);
}

function expect($condition, $message) {
	global $tests;
	$tests++;
	if (!$condition) throw new RuntimeException($message);
}

expect('auto' === invoke_private($plugin, 'meta_choice', 10, UP_Strange_Place_Picker::META_RECORD_TYPE, 'record_type'), 'Existing posts must default to Auto.');

update_post_meta(10, UP_Strange_Place_Picker::META_LAT, '41.7151');
update_post_meta(10, UP_Strange_Place_Picker::META_LNG, '44.8271');
update_post_meta(10, UP_Strange_Place_Picker::META_INFERRED_LAT, '0');
update_post_meta(10, UP_Strange_Place_Picker::META_INFERRED_LNG, '0');
$coords = invoke_private($plugin, 'coords', 10);
expect('exact' === $coords['source'] && 41.7151 === $coords['lat'], 'Exact coordinates must override inferred coordinates.');

update_post_meta(11, UP_Strange_Place_Picker::META_INFERRED_LAT, '47.68762');
update_post_meta(11, UP_Strange_Place_Picker::META_INFERRED_LNG, '-3.18483');
invoke_private($plugin, 'maybe_store_inferred_coords', 11, 'No recognized location', array(), array());
expect('47.68762' === get_post_meta(11, UP_Strange_Place_Picker::META_INFERRED_LAT, true), 'A failed re-inference must not erase existing inferred coordinates.');

expect('post' === invoke_private($plugin, 'exclusion_reason', 'single', 'exclude', array(), array(), 10, false), 'Explicit Exclude must win.');
expect('' === invoke_private($plugin, 'exclusion_reason', 'non_place', 'include', array(4), array(4), 0, true), 'Explicit Include must override all automatic exclusions.');
expect('category' === invoke_private($plugin, 'exclusion_reason', 'single', 'auto', array(4), array(4), 10, false), 'An Auto post in an excluded category must be excluded.');
expect('non_place' === invoke_private($plugin, 'exclusion_reason', 'non_place', 'auto', array(), array(), 10, false), 'Non-place records must normally be excluded.');
expect('' === invoke_private($plugin, 'exclusion_reason', 'multi', 'auto', array(), array(), 10, false), 'Multi-place records must retain existing inclusion behavior.');
expect('existing_rules' === invoke_private($plugin, 'exclusion_reason', 'auto', 'auto', array(), array(), 3, false), 'Existing score filtering must remain active.');

update_post_meta(20, UP_Strange_Place_Picker::META_MANUAL_TYPE, 'Historic site');
$fields = invoke_private($plugin, 'structured_fields', 20, 'Natural wonder');
expect('Historic site' === $fields['type'], 'Manual Place Type must override inference.');
delete_post_meta(20, UP_Strange_Place_Picker::META_MANUAL_TYPE);
$fields = invoke_private($plugin, 'structured_fields', 20, 'Natural wonder');
expect('Natural wonder' === $fields['type'], 'Empty Place Type must fall back to inference.');

$_POST = array(
	'up_spp_place_meta_nonce' => 'valid',
	'up_spp_record_type' => 'single',
	'up_spp_inclusion' => 'auto',
	'up_spp_manual_place_type' => 'Natural wonder',
	'up_spp_cost' => 'free',
	'up_spp_opening_status' => 'seasonal',
	'up_spp_dog_friendly' => 'restrictions',
	'up_spp_environment' => 'outdoor',
	'up_spp_lat' => '95',
	'up_spp_lng' => '44',
	'up_spp_place_label' => 'Test Place',
	'up_spp_last_verified' => '2026-09-08',
);
$plugin->save_place_meta(20, (object) array('post_status' => 'publish'));
expect('single' === get_post_meta(20, UP_Strange_Place_Picker::META_RECORD_TYPE, true), 'Single Place must save.');
expect('free' === get_post_meta(20, UP_Strange_Place_Picker::META_COST, true), 'Structured fields must save.');
expect('' === get_post_meta(20, UP_Strange_Place_Picker::META_LAT, true), 'Out-of-range coordinates must not save.');
expect('2026-09-08' === get_post_meta(20, UP_Strange_Place_Picker::META_LAST_VERIFIED, true), 'Valid Last Verified date must save.');
expect(isset($GLOBALS['up_scheduled'][UP_Strange_Place_Picker::REFRESH_HOOK]), 'Explorer metadata changes must schedule a cache refresh.');

update_post_meta(20, UP_Strange_Place_Picker::META_RECORD_TYPE, 'multi');
expect('multi' === invoke_private($plugin, 'meta_choice', 20, UP_Strange_Place_Picker::META_RECORD_TYPE, 'record_type'), 'Multi-place classification must reload.');
update_post_meta(20, UP_Strange_Place_Picker::META_RECORD_TYPE, 'non_place');
expect('non_place' === invoke_private($plugin, 'meta_choice', 20, UP_Strange_Place_Picker::META_RECORD_TYPE, 'record_type'), 'Non-place classification must reload.');

$sample_posts = array();
for ($i = 1; $i <= 30; $i++) {
	$sample_posts[] = array('id' => $i, 'title' => 'Place ' . $i, 'url' => 'https://example.test/place-' . $i, 'excerpt' => 'Excerpt', 'image' => '', 'regions' => array('Anywhere'), 'regionLabel' => 'Anywhere', 'moods' => array('Beautiful'), 'moodLabel' => 'Beautiful', 'type' => 'Unusual place', 'bestFor' => 'testing');
}
$full_index = array('posts' => $sample_posts, 'moods' => array('Any', 'Beautiful'), 'regionGroups' => array(), 'version' => 'test');
$bootstrap = invoke_private($plugin, 'bootstrap_data', $full_index);
expect(20 === count($bootstrap['posts']) && 30 === $bootstrap['postCount'] && true === $bootstrap['partial'], 'The shortcode bootstrap must be limited to 20 posts while preserving the total count.');
set_transient(UP_Strange_Place_Picker::LEGACY_CACHE_KEY, $full_index);
expect(30 === count($plugin->get_index()['posts']), 'Upgrade must use the legacy cache while scheduling a new background rebuild.');
set_transient(UP_Strange_Place_Picker::BOOTSTRAP_CACHE_KEY, $bootstrap);
$html = $plugin->shortcode();
expect(false !== strpos($html, 'data-up-spp-root'), 'The shortcode must render from the compact cache.');
expect(strlen($html) < 50000, 'The compact shortcode HTML must stay lightweight in the smoke fixture.');

echo "OK: {$tests} plugin smoke checks passed.\n";
