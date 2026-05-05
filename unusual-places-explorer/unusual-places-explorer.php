<?php
/**
 * Plugin Name: Unusual Places Explorer
 * Description: Adds The Strange Place Picker shortcode for discovering published unusualplaces.org articles.
 * Version: 1.0.0
 * Author: Unusual Places
 * Text Domain: unusual-places-explorer
 */

if (!defined('ABSPATH')) {
	exit;
}

final class UP_Strange_Place_Picker {
	const VERSION = '1.0.0';
	const SHORTCODE = 'up_strange_place_picker';
	const CACHE_KEY = 'up_spp_index_v100';
	const CRON_HOOK = 'up_spp_monthly_rebuild';
	const META_LAT = '_up_spp_lat';
	const META_LNG = '_up_spp_lng';
	const META_LABEL = '_up_spp_place_label';

	private static $instance = null;

	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode(self::SHORTCODE, array($this, 'shortcode'));
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
		add_action(self::CRON_HOOK, array($this, 'rebuild_cache'));
		add_action('save_post_post', array($this, 'clear_cache_on_post_save'), 20, 3);
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
		add_action('save_post_post', array($this, 'save_place_meta'), 10, 2);
	}

	public static function activate() {
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'monthly', self::CRON_HOOK);
		}
		self::instance()->rebuild_cache();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook(self::CRON_HOOK);
		delete_transient(self::CACHE_KEY);
	}

	public function register_assets() {
		wp_register_style('up-strange-place-picker', plugins_url('assets/picker.css', __FILE__), array(), self::VERSION);
		wp_register_script('up-strange-place-picker', plugins_url('assets/picker.js', __FILE__), array(), self::VERSION, true);
	}

	public function shortcode() {
		$data = $this->get_index();
		$posts = isset($data['posts']) && is_array($data['posts']) ? $data['posts'] : array();
		$starter = !empty($posts) ? $posts[0] : null;
		$uid = 'up-spp-' . wp_rand(1000, 999999);

		wp_enqueue_style('up-strange-place-picker');
		wp_enqueue_script('up-strange-place-picker');

		ob_start();
		?>
		<section id="<?php echo esc_attr($uid); ?>" class="up-spp" data-up-spp-root>
			<div class="up-spp__intro">
				<p class="up-spp__eyebrow">Unusual places finder</p>
				<h2>The Strange Place Picker</h2>
				<p>Find a strange, beautiful, creepy, or forgotten place from the Unusual Places archive. Choose a mood, pick a region, or use your location to discover an unusual travel idea worth reading about.</p>
			</div>

			<div class="up-spp__panel" aria-label="Find unusual travel ideas">
				<div class="up-spp__group">
					<h3>What kind of strange are you feeling?</h3>
					<div class="up-spp__buttons" data-up-spp-moods>
						<?php foreach ($data['moods'] as $mood) : ?>
							<button type="button" class="up-spp__chip<?php echo 'Any' === $mood ? ' is-active' : ''; ?>" data-mood="<?php echo esc_attr($mood); ?>" aria-pressed="<?php echo 'Any' === $mood ? 'true' : 'false'; ?>"><?php echo esc_html($mood); ?></button>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="up-spp__group">
					<h3>Where do you want to go?</h3>
					<div class="up-spp__buttons up-spp__region-top" data-up-spp-region-groups>
						<?php foreach (array('Anywhere', 'USA', 'Europe', 'Asia', 'Australia', 'South America') as $region) : ?>
							<button type="button" class="up-spp__chip up-spp__chip--top<?php echo 'Anywhere' === $region ? ' is-active' : ''; ?>" data-region-group="<?php echo esc_attr($region); ?>" data-region="<?php echo esc_attr($region); ?>" aria-pressed="<?php echo 'Anywhere' === $region ? 'true' : 'false'; ?>"><?php echo esc_html($region); ?></button>
						<?php endforeach; ?>
					</div>
					<div class="up-spp__country-picker" data-up-spp-country-wrap hidden><p data-up-spp-country-label>Choose a country</p><div class="up-spp__buttons" data-up-spp-countries></div></div>
					<div class="up-spp__geo"><button type="button" class="up-spp__geo-button" data-up-spp-location aria-pressed="false">Use my location</button><p>Used only to suggest nearby unusual places. Nothing is saved.</p></div>
					<div class="up-spp__radius">
						<label for="<?php echo esc_attr($uid); ?>-radius"><span>Search radius</span><strong data-up-spp-radius-label>250 km</strong></label>
						<input id="<?php echo esc_attr($uid); ?>-radius" type="range" min="25" max="2000" step="25" value="250" data-up-spp-radius>
						<div class="up-spp__radius-presets" aria-label="Quick radius choices"><button type="button" data-up-spp-radius-preset="50">50 km</button><button type="button" data-up-spp-radius-preset="250" class="is-active">250 km</button><button type="button" data-up-spp-radius-preset="750">750 km</button><button type="button" data-up-spp-radius-preset="2000">2000 km</button></div>
						<p>Start close. Increase the radius only when there are not enough nearby articles yet.</p>
					</div>
				</div>
				<button type="button" class="up-spp__find" data-up-spp-find>Find My Strange Place</button>
				<p class="up-spp__status" data-up-spp-status aria-live="polite"></p>
				<details class="up-spp__mobile-help"><summary>Location not working on mobile?</summary><p>Open this page on HTTPS, then allow location for your browser. On iPhone Safari, check Settings &gt; Privacy &amp; Security &gt; Location Services &gt; Safari Websites.</p></details>
			</div>

			<div class="up-spp__results" data-up-spp-results aria-live="polite"><?php echo $starter ? $this->render_static_card($starter) : '<p class="up-spp__empty">No published unusual-place articles were found yet.</p>'; ?></div>

			<?php if (!empty($posts)) : ?>
				<div class="up-spp__crawl"><h3>Popular unusual places from the archive</h3><ul><?php foreach (array_slice($posts, 0, 10) as $post) : ?><li><a href="<?php echo esc_url($post['url']); ?>"><?php echo esc_html($post['title']); ?></a></li><?php endforeach; ?></ul></div>
			<?php endif; ?>

			<div class="up-spp__affiliate" aria-label="Future travel planning links"><div>Stay nearby</div><div>Find tours nearby</div><div>Rent a car for this route</div></div>
			<div class="up-spp__faq"><h3>Strange Place Picker FAQ</h3><details><summary>What is The Strange Place Picker?</summary><p>It is a discovery tool for finding strange places to visit, weird travel ideas, and unusual stories from the Unusual Places archive.</p></details><details><summary>Can it find unusual places near me?</summary><p>Yes, when articles have coordinates or a place can be approximately inferred. Your location stays in your browser.</p></details><details><summary>Does it save my location?</summary><p>No. The plugin does not store visitor coordinates, create cookies, or send your location to third-party APIs.</p></details><details><summary>Are these real places?</summary><p>Yes. Results link to published Unusual Places articles about real destinations, landmarks, oddities, and travel stories.</p></details></div>
			<script type="application/json" data-up-spp-json><?php echo wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
		</section>
		<?php
		return ob_get_clean();
	}

	private function render_static_card($post) {
		ob_start();
		?>
		<article class="up-spp__card up-spp__card--main">
			<?php if (!empty($post['image'])) : ?><a href="<?php echo esc_url($post['url']); ?>" class="up-spp__image-link"><img src="<?php echo esc_url($post['image']); ?>" alt="<?php echo esc_attr($post['title']); ?>" loading="eager"></a><?php endif; ?>
			<div class="up-spp__card-body"><p class="up-spp__meta"><?php echo esc_html($post['regionLabel']); ?> · <?php echo esc_html($post['moodLabel']); ?> · <?php echo esc_html($post['type']); ?></p><h3><a href="<?php echo esc_url($post['url']); ?>"><?php echo esc_html($post['title']); ?></a></h3><p><?php echo esc_html($post['excerpt']); ?></p><p class="up-spp__best">Best for <?php echo esc_html($post['bestFor']); ?>.</p><div class="up-spp__actions"><a class="up-spp__read" href="<?php echo esc_url($post['url']); ?>">Read the full article</a><button type="button" class="up-spp__another" data-up-spp-another>Show me another</button></div></div>
		</article>
		<?php
		return ob_get_clean();
	}

	private function terms($post_id, $taxonomy) {
		$terms = get_the_terms($post_id, $taxonomy);
		return is_wp_error($terms) || empty($terms) ? array() : array_values(wp_list_pluck($terms, 'name'));
	}

	private function keyword_score($text, $word) {
		$word = strtolower(trim($word));
		if ('' === $word) return 0;
		$pattern = '/(?<![a-z0-9])' . preg_quote($word, '/') . '(?![a-z0-9])/';
		return preg_match($pattern, $text) ? (false !== strpos($word, ' ') ? 3 : 1) : 0;
	}

	private function contains($text, $words) {
		foreach ($words as $word) {
			if ($this->keyword_score($text, $word) > 0) return true;
		}
		return false;
	}

	private function mood_keywords() {
		return array(
			'Beautiful' => array('beautiful', 'beauty', 'scenic', 'breathtaking', 'picturesque', 'stunning', 'majestic', 'spectacular', 'dramatic landscape', 'idyllic', 'crystal', 'turquoise', 'colorful', 'colourful', 'vibrant', 'surreal', 'dreamlike', 'natural wonder', 'landscape', 'viewpoint', 'panorama', 'cliff', 'lake', 'mountain', 'island', 'sanctuary', 'waterfall', 'beach', 'oasis', 'forest', 'garden', 'valley', 'coast', 'canyon', 'lagoon', 'cave', 'spring', 'gorge', 'fjord', 'glacier', 'desert', 'rainbow', 'wildflowers', 'sunset'),
			'Creepy' => array('creepy', 'haunted', 'ghost', 'ghostly', 'spooky', 'eerie', 'sinister', 'unsettling', 'chilling', 'cemetery', 'graveyard', 'tomb', 'catacomb', 'burial', 'skull', 'bones', 'macabre', 'abandoned hospital', 'prison', 'asylum', 'curse', 'cursed', 'occult', 'crypt', 'death', 'dead', 'funeral', 'mourning', 'haunting', 'paranormal', 'crematorium', 'ossuary'),
			'Forgotten' => array('forgotten', 'lost', 'abandoned', 'ghost town', 'ruins', 'ruined', 'derelict', 'deserted', 'empty', 'decayed', 'decaying', 'soviet', 'relic', 'remnant', 'remote', 'overgrown', 'left behind', 'disused', 'neglected', 'vanished', 'hidden history', 'time capsule', 'once thriving', 'former', 'old railway', 'old road'),
			'Fairytale' => array('fairytale', 'fairy tale', 'storybook', 'castle', 'village', 'medieval', 'charming', 'magical', 'enchanted', 'whimsical', 'gingerbread', 'alpine', 'colorful town', 'colourful town', 'cobbled', 'cobblestone', 'old town', 'palace', 'tower', 'turret', 'fortified village', 'romantic', 'dream-land', 'dreamland', 'wonderland'),
			'Movie-like' => array('film', 'movie', 'filming location', 'cinematic', 'james bond', 'star wars', 'hollywood', 'movie set', 'film set', 'movie scene', 'film scene', 'featured in', 'blockbuster', 'screen location', 'iconic film', 'on screen', 'sci-fi', 'science fiction', 'fantasy location', 'looks like a movie', 'movie-like'),
			'Ancient' => array('ancient', 'prehistoric', 'archaeological', 'archaeology', 'ruins', 'temple', 'monument', 'pyramid', 'megalith', 'roman', 'byzantine', 'neolithic', 'medieval', 'fortress', 'monastery', 'cave town', 'tomb', 'petroglyph', 'dolmen', 'stone circle', 'burial mound', 'civilization', 'civilisation', 'historic', 'history', 'heritage', 'oldest', 'centuries-old'),
			'Abandoned' => array('abandoned', 'derelict', 'ghost town', 'deserted', 'empty', 'ruins', 'decay', 'decaying', 'disused', 'forsaken', 'left behind', 'closed', 'crumbling', 'former', 'forgotten', 'vacant', 'desolate', 'reclaimed by nature', 'industrial ruin', 'abandoned building', 'abandoned place', 'abandoned town', 'abandoned village'),
			'Roadside weird' => array('roadside', 'oddity', 'bizarre', 'quirky', 'giant', 'weird', 'strange attraction', 'roadside attraction', 'novelty', 'worlds largest', "world's largest", 'kitsch', 'neon', 'theme park', 'trippy', 'eccentric', 'peculiar', 'outsider art', 'folk art', 'giant object', 'road trip', 'must-see attraction'),
			'Peaceful but strange' => array('remote', 'quiet', 'hidden', 'lonely', 'surreal', 'peaceful', 'isolated', 'offbeat', 'secluded', 'tranquil', 'silent', 'wilderness', 'little-known', 'secret', 'otherworldly', 'unusual landscape', 'hidden gem', 'retreat', 'slow travel', 'escape', 'solitude', 'calm', 'serene', 'far away', 'hard to reach', 'edge of the world'),
		);
	}

	private function moods($text) {
		$scores = array();
		foreach ($this->mood_keywords() as $mood => $words) {
			$scores[$mood] = 0;
			foreach ($words as $word) $scores[$mood] += $this->keyword_score($text, $word);
		}
		if ($scores['Abandoned'] > 0) $scores['Forgotten'] += 1;
		if ($scores['Ancient'] > 0 && false !== strpos($text, 'ruin')) $scores['Forgotten'] += 1;
		if ($scores['Creepy'] > 0 && (false !== strpos($text, 'cemetery') || false !== strpos($text, 'tomb') || false !== strpos($text, 'burial'))) $scores['Ancient'] += 1;
		arsort($scores);
		$moods = array();
		foreach ($scores as $mood => $score) {
			if ($score > 0) $moods[] = $mood;
			if (count($moods) >= 4) break;
		}
		return empty($moods) ? array('Peaceful but strange') : array_values(array_unique($moods));
	}

	private function type($text) {
		$types = array('Natural wonder' => array('lake', 'mountain', 'waterfall', 'island', 'beach', 'forest', 'desert', 'cave', 'volcano', 'rock formation'), 'Abandoned place' => array('abandoned', 'ghost town', 'derelict', 'ruins'), 'Historic site' => array('ancient', 'monastery', 'church', 'temple', 'castle', 'fortress', 'palace', 'monument', 'cathedral'), 'Architectural oddity' => array('building', 'house', 'architecture', 'bridge', 'tower'), 'Roadside attraction' => array('roadside', 'oddity', 'giant', 'quirky'));
		foreach ($types as $type => $words) if ($this->contains($text, $words)) return $type;
		return 'Unusual place';
	}

	private function regions($categories, $text) {
		$regions = array();
		$category_lc = array_map('strtolower', $categories);
		$add = function($region) use (&$regions) { if (!in_array($region, $regions, true)) $regions[] = $region; };
		$usa_terms = array('usa', 'united states', 'north america', 'california', 'florida', 'arizona', 'texas', 'new york', 'oregon', 'nevada', 'tennessee', 'utah', 'alaska', 'hawaii', 'colorado', 'washington');
		$europe_terms = array('europe', 'georgia', 'italy', 'france', 'uk', 'united kingdom', 'england', 'scotland', 'wales', 'spain', 'germany', 'iceland', 'portugal', 'norway', 'greece', 'ireland', 'turkey', 'russia', 'netherlands', 'croatia', 'sweden', 'poland', 'switzerland', 'austria', 'denmark', 'serbia');
		if (array_intersect($category_lc, $usa_terms) || preg_match('/\b(usa|united states|oregon|california|florida|arizona|texas|new york)\b/', $text)) $add('USA');
		if (in_array('europe', $category_lc, true) || array_intersect($category_lc, $europe_terms)) $add('Europe');
		foreach (array('Georgia', 'Italy', 'France', 'UK', 'Spain', 'Germany', 'Iceland', 'Portugal', 'Norway', 'Greece', 'Ireland', 'Turkey', 'Russia', 'Japan', 'China', 'India', 'Thailand', 'Indonesia', 'Vietnam', 'Cambodia', 'Australia', 'New Zealand', 'Brazil', 'Argentina', 'Chile', 'Peru', 'Bolivia') as $region) {
			if (in_array(strtolower($region), $category_lc, true) || false !== strpos($text, ' ' . strtolower($region))) $add($region);
		}
		foreach ($categories as $category) {
			if (in_array($category, array('Travel', 'North America', 'Europe', 'Asia', 'South America', 'Africa', 'Middle East', 'Oceania', 'Uncategorized'), true)) continue;
			$add($category);
		}
		return empty($regions) ? array('Anywhere') : $regions;
	}

	private function coords($post_id) {
		$lat = get_post_meta($post_id, self::META_LAT, true);
		$lng = get_post_meta($post_id, self::META_LNG, true);
		if (!is_numeric($lat) || !is_numeric($lng)) {
			$meta = get_post_meta($post_id, '_field_trip_location_meta', true);
			if (is_array($meta)) {
				$lat = isset($meta['lat']) ? $meta['lat'] : $lat;
				$lng = isset($meta['lng']) ? $meta['lng'] : $lng;
			} elseif (is_string($meta) && '' !== $meta) {
				if (preg_match('/s:3:"lat";[sd]:([0-9.\-]+)/', $meta, $match)) $lat = $match[1];
				if (preg_match('/s:3:"lng";[sd]:([0-9.\-]+)/', $meta, $match)) $lng = $match[1];
				if ((!is_numeric($lat) || !is_numeric($lng)) && preg_match('/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $meta, $match)) { $lat = $match[1]; $lng = $match[2]; }
			}
		}
		if (!is_numeric($lat) || !is_numeric($lng)) {
			foreach (array('lat', 'latitude', '_lat', '_latitude', 'geo_latitude', 'geographic_latitude') as $key) { $value = get_post_meta($post_id, $key, true); if (is_numeric($value)) { $lat = $value; break; } }
			foreach (array('lng', 'lon', 'longitude', '_lng', '_lon', '_longitude', 'geo_longitude', 'geographic_longitude') as $key) { $value = get_post_meta($post_id, $key, true); if (is_numeric($value)) { $lng = $value; break; } }
		}
		return is_numeric($lat) && is_numeric($lng) ? array('lat' => (float) $lat, 'lng' => (float) $lng) : null;
	}

	private function best_for($moods, $type) {
		if (in_array('Creepy', $moods, true)) return 'curious travelers who like eerie stories';
		if (in_array('Fairytale', $moods, true)) return 'slow wandering, photos, and storybook atmosphere';
		if (in_array('Ancient', $moods, true)) return 'history lovers and archaeology-minded travelers';
		if ('Natural wonder' === $type) return 'nature lovers and scenic detours';
		return 'a handpicked unusual travel idea';
	}

	public function get_index() {
		$cached = get_transient(self::CACHE_KEY);
		return is_array($cached) && !empty($cached['posts']) ? $cached : $this->rebuild_cache();
	}

	public function rebuild_cache() {
		$query = new WP_Query(array('post_type' => 'post', 'post_status' => 'publish', 'post_password' => '', 'posts_per_page' => 2000, 'orderby' => 'modified', 'order' => 'DESC', 'ignore_sticky_posts' => true, 'no_found_rows' => true));
		$posts = array(); $region_counts = array(); $mood_counts = array();
		$promo_pattern = '/\b(sponsored|promo|casino|insurance|car rental|rent a car|airbnb|vacation rental|loan|essay|write for us|guest post|coupon|discount|moving company|shipping|visa|travel tips|guide to choosing|best ways to|things to consider)\b/i';
		$place_pattern = '/\b(castle|island|village|city|town|monastery|church|temple|ruins?|cave|bridge|road|lake|mountain|forest|park|museum|palace|tower|cemetery|tomb|monument|house|building|beach|desert|waterfall|cliff|valley|fortress|sanctuary|ghost town|abandoned|garden|statue|tunnel|railway|station|mine|volcano|rock|pyramid|cathedral|chapel|pillar)\b/i';
		foreach ($query->posts as $post) {
			$post_id = $post->ID;
			$categories = $this->terms($post_id, 'category'); $tags = $this->terms($post_id, 'post_tag');
			$content_text = wp_strip_all_tags($post->post_title . ' ' . $post->post_title . ' ' . $post->post_excerpt . ' ' . wp_trim_words($post->post_content, 260, '') . ' ' . implode(' ', $categories) . ' ' . implode(' ', $categories) . ' ' . implode(' ', $tags) . ' ' . implode(' ', $tags));
			$text = strtolower($content_text); $non_generic_categories = array_diff($categories, array('Travel', 'Uncategorized'));
			$travel_only = in_array('Travel', $categories, true) && empty($non_generic_categories); $is_promo = (bool) preg_match($promo_pattern, $content_text); $coords = $this->coords($post_id);
			$score = 0; $score += has_post_thumbnail($post_id) ? 2 : 0; $score += $coords ? 5 : 0; $score += preg_match($place_pattern, $content_text) ? 3 : 0; $score += !empty($non_generic_categories) ? 2 : 0; $score += !empty($tags) ? 1 : 0; $score -= $travel_only ? 2 : 0; $score -= $is_promo ? 6 : 0;
			if ($score < 4 || $is_promo) continue;
			$moods = $this->moods($text); $type = $this->type($text); $regions = $this->regions($categories, $text); $excerpt = get_the_excerpt($post_id);
			if ('' === $excerpt) $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 24, '...');
			foreach ($regions as $region) if ('Anywhere' !== $region) $region_counts[$region] = isset($region_counts[$region]) ? $region_counts[$region] + 1 : 1;
			foreach ($moods as $mood) $mood_counts[$mood] = isset($mood_counts[$mood]) ? $mood_counts[$mood] + 1 : 1;
			$posts[] = array('id' => $post_id, 'title' => get_the_title($post_id), 'url' => get_permalink($post_id), 'excerpt' => wp_trim_words($excerpt, 28, '...'), 'image' => get_the_post_thumbnail_url($post_id, 'large'), 'regions' => $regions, 'regionLabel' => $regions[0], 'moods' => $moods, 'moodLabel' => $moods[0], 'type' => $type, 'bestFor' => $this->best_for($moods, $type), 'lat' => $coords ? $coords['lat'] : null, 'lng' => $coords ? $coords['lng'] : null, 'placeLabel' => get_post_meta($post_id, self::META_LABEL, true), '_score' => $score);
		}
		wp_reset_postdata();
		usort($posts, function($a, $b) { if (($a['lat'] !== null) !== ($b['lat'] !== null)) return $a['lat'] !== null ? -1 : 1; return $b['_score'] <=> $a['_score']; });
		$posts = array_slice($posts, 0, 1200); foreach ($posts as &$post) unset($post['_score']); unset($post);
		$data = array('posts' => $posts, 'regionGroups' => $this->region_groups($region_counts), 'moods' => array('Any', 'Beautiful', 'Creepy', 'Forgotten', 'Fairytale', 'Movie-like', 'Ancient', 'Abandoned', 'Roadside weird', 'Peaceful but strange'), 'coordinateCount' => count(array_filter($posts, function($post) { return null !== $post['lat'] && null !== $post['lng']; })), 'moodCounts' => $mood_counts, 'regionCounts' => $region_counts, 'builtAt' => current_time('mysql'), 'version' => self::VERSION);
		set_transient(self::CACHE_KEY, $data, 35 * DAY_IN_SECONDS); update_option('up_spp_last_rebuild', current_time('mysql'), false); return $data;
	}

	private function region_groups($region_counts) {
		$country_groups = array('USA' => array('California','Florida','Arizona','Texas','New York','Oregon','Nevada','Tennessee','Utah','Alaska','Hawaii','Colorado','Washington','Pennsylvania','Ohio','Michigan','Illinois','North Carolina','South Carolina','Louisiana','New Mexico','Massachusetts','Virginia','Maryland','Maine','Montana','Wyoming','Idaho','Kansas','Missouri','Alabama','Kentucky','Indiana','Wisconsin','Minnesota','Georgia'), 'Europe' => array('Italy','France','UK','Spain','Germany','Georgia','Iceland','Portugal','Norway','Greece','Ireland','Netherlands','Croatia','Sweden','Poland','Switzerland','Austria','Denmark','Serbia','Russia','Turkey','Scotland','England','Wales','Belgium','Czech Republic','Romania','Bulgaria','Hungary','Slovenia','Slovakia','Finland','Estonia','Latvia','Lithuania','Ukraine','Armenia','Azerbaijan','Malta','Cyprus'), 'Asia' => array('Japan','China','India','Thailand','Indonesia','Vietnam','Cambodia','Malaysia','Singapore','Philippines','South Korea','Sri Lanka','Nepal','Taiwan','Hong Kong','Mongolia','Kazakhstan','Uzbekistan','Kyrgyzstan','Laos','Myanmar','Turkey','Armenia','Azerbaijan','Georgia'), 'Australia' => array('Australia','New Zealand'), 'South America' => array('Brazil','Argentina','Chile','Peru','Bolivia','Colombia','Guyana','Uruguay','Ecuador','Venezuela','Paraguay','Suriname'));
		$groups = array('Anywhere' => array('label'=>'Anywhere','region'=>'Anywhere','countries'=>array()), 'USA' => array('label'=>'USA','region'=>'USA','countries'=>array()), 'Europe' => array('label'=>'Europe','region'=>'Europe','countries'=>array()), 'Asia' => array('label'=>'Asia','region'=>'Asia','countries'=>array()), 'Australia' => array('label'=>'Australia','region'=>'Australia','countries'=>array()), 'South America' => array('label'=>'South America','region'=>'South America','countries'=>array()));
		foreach ($country_groups as $group => $countries) foreach ($countries as $country) if (isset($region_counts[$country]) && !in_array($country, $groups[$group]['countries'], true)) $groups[$group]['countries'][] = $country;
		foreach (array('Europe'=>array('Italy','France','UK','Georgia'), 'USA'=>array('California','Florida','Arizona','Texas','New York'), 'Asia'=>array('Japan','China','India','Thailand'), 'Australia'=>array('Australia'), 'South America'=>array('Brazil','Argentina','Chile')) as $group => $fallback) if (empty($groups[$group]['countries'])) $groups[$group]['countries'] = $fallback;
		return $groups;
	}

	public function clear_cache_on_post_save($post_id, $post, $update) { if (!wp_is_post_revision($post_id) && !wp_is_post_autosave($post_id)) delete_transient(self::CACHE_KEY); }
	public function admin_menu() { add_options_page('Unusual Places Explorer', 'Unusual Places Explorer', 'manage_options', 'up-strange-place-picker', array($this, 'admin_page')); }
	public function admin_page() {
		if (!current_user_can('manage_options')) return;
		if (isset($_POST['up_spp_rebuild']) && check_admin_referer('up_spp_rebuild')) { $this->rebuild_cache(); echo '<div class="updated"><p>Strange Place Picker cache rebuilt.</p></div>'; }
		$data = $this->get_index();
		?>
		<div class="wrap"><h1>Unusual Places Explorer</h1><p>Shortcode: <code>[up_strange_place_picker]</code></p><form method="post"><?php wp_nonce_field('up_spp_rebuild'); ?><p><button class="button button-primary" name="up_spp_rebuild" value="1">Rebuild picker cache now</button></p></form><h2>Index Status</h2><table class="widefat striped" style="max-width:760px"><tbody><tr><th>Indexed posts</th><td><?php echo esc_html(count($data['posts'])); ?></td></tr><tr><th>Posts with exact coordinates</th><td><?php echo esc_html($data['coordinateCount']); ?></td></tr><tr><th>Last rebuilt</th><td><?php echo esc_html(isset($data['builtAt']) ? $data['builtAt'] : get_option('up_spp_last_rebuild', 'Never')); ?></td></tr></tbody></table><h2>Mood Coverage</h2><table class="widefat striped" style="max-width:760px"><tbody><?php foreach ($data['moods'] as $mood) : if ('Any' === $mood) continue; ?><tr><th><?php echo esc_html($mood); ?></th><td><?php echo esc_html(isset($data['moodCounts'][$mood]) ? $data['moodCounts'][$mood] : 0); ?></td></tr><?php endforeach; ?></tbody></table><p>Add exact coordinates on the post edit screen in the “Strange Place Picker Coordinates” box. Exact coordinates make mobile nearby search much better than inferred locations.</p></div>
		<?php
	}
	public function add_meta_boxes() { add_meta_box('up-spp-coordinates', 'Strange Place Picker Coordinates', array($this, 'render_meta_box'), 'post', 'side', 'default'); }
	public function render_meta_box($post) { wp_nonce_field('up_spp_save_place_meta', 'up_spp_place_meta_nonce'); $lat = get_post_meta($post->ID, self::META_LAT, true); $lng = get_post_meta($post->ID, self::META_LNG, true); $label = get_post_meta($post->ID, self::META_LABEL, true); ?><p><label for="up_spp_place_label">Place label</label><br><input id="up_spp_place_label" name="up_spp_place_label" type="text" value="<?php echo esc_attr($label); ?>" class="widefat" placeholder="Vardzia, Georgia"></p><p><label for="up_spp_lat">Latitude</label><br><input id="up_spp_lat" name="up_spp_lat" type="text" value="<?php echo esc_attr($lat); ?>" class="widefat" placeholder="41.381"></p><p><label for="up_spp_lng">Longitude</label><br><input id="up_spp_lng" name="up_spp_lng" type="text" value="<?php echo esc_attr($lng); ?>" class="widefat" placeholder="43.284"></p><p class="description">Used only for sorting visitor-side nearby results. Visitor locations are not saved.</p><?php }
	public function save_place_meta($post_id, $post) {
		if (!isset($_POST['up_spp_place_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['up_spp_place_meta_nonce'])), 'up_spp_save_place_meta') || !current_user_can('edit_post', $post_id)) return;
		foreach (array(self::META_LAT => 'up_spp_lat', self::META_LNG => 'up_spp_lng') as $meta_key => $field) { $value = isset($_POST[$field]) ? trim(sanitize_text_field(wp_unslash($_POST[$field]))) : ''; if ('' === $value) delete_post_meta($post_id, $meta_key); elseif (is_numeric($value)) update_post_meta($post_id, $meta_key, (string) (float) $value); }
		$label = isset($_POST['up_spp_place_label']) ? sanitize_text_field(wp_unslash($_POST['up_spp_place_label'])) : ''; if ('' === $label) delete_post_meta($post_id, self::META_LABEL); else update_post_meta($post_id, self::META_LABEL, $label); delete_transient(self::CACHE_KEY);
	}
}

add_filter('cron_schedules', function($schedules) { if (!isset($schedules['monthly'])) $schedules['monthly'] = array('interval' => 30 * DAY_IN_SECONDS, 'display' => 'Once Monthly'); return $schedules; });
register_activation_hook(__FILE__, array('UP_Strange_Place_Picker', 'activate'));
register_deactivation_hook(__FILE__, array('UP_Strange_Place_Picker', 'deactivate'));
UP_Strange_Place_Picker::instance();
