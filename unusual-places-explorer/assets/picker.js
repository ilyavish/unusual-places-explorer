(function(){
	'use strict';

	document.querySelectorAll('[data-up-spp-root]').forEach(function(root) {
		var jsonEl = root.querySelector('[data-up-spp-json]');
		if (!jsonEl) return;

		var data;
		try {
			data = JSON.parse(jsonEl.textContent || '{}');
		} catch (error) {
			return;
		}

		var posts = Array.isArray(data.posts) ? data.posts : [];
		var regionGroups = data.regionGroups || {};
		var state = { mood: 'Any', region: 'Anywhere', lat: null, lng: null, locationMode: false, lastId: null, radiusKm: 250, shownLocationIds: [], detectedCountry: '', detectedBroad: '', distanceCacheKey: '', exactDistanceCache: {}, approxCache: {} };
		var statusEl = root.querySelector('[data-up-spp-status]');
		var resultsEl = root.querySelector('[data-up-spp-results]');
		var countryWrap = root.querySelector('[data-up-spp-country-wrap]');
		var countryList = root.querySelector('[data-up-spp-countries]');
		var countryLabel = root.querySelector('[data-up-spp-country-label]');
		var radiusInput = root.querySelector('[data-up-spp-radius]');
		var radiusLabel = root.querySelector('[data-up-spp-radius-label]');

		function setStatus(message) { if (statusEl) statusEl.textContent = message || ''; }
		function esc(value) {
			return String(value || '').replace(/[&<>"']/g, function(char) {
				return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
			});
		}
		function hasMood(post, mood) { return mood === 'Any' || (Array.isArray(post.moods) && post.moods.indexOf(mood) !== -1); }
		function hasRegion(post, region) {
			if (region === 'Anywhere') return true;
			var postRegions = Array.isArray(post.regions) ? post.regions : [];
			if (postRegions.indexOf(region) !== -1) return true;
			var group = regionGroups[region] || {};
			var countries = Array.isArray(group.countries) ? group.countries : [];
			return countries.some(function(country) { return postRegions.indexOf(country) !== -1; });
		}
		function distanceKm(aLat, aLng, bLat, bLng) {
			var radius = 6371, toRad = function(deg){ return deg * Math.PI / 180; };
			var dLat = toRad(bLat - aLat), dLng = toRad(bLng - aLng), lat1 = toRad(aLat), lat2 = toRad(bLat);
			var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
			return radius * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
		}
		function refreshDistanceCacheKey() {
			if (typeof state.lat !== 'number' || typeof state.lng !== 'number') return;
			var key = state.lat.toFixed(3) + ',' + state.lng.toFixed(3);
			if (key !== state.distanceCacheKey) {
				state.distanceCacheKey = key;
				state.exactDistanceCache = {};
				state.approxCache = {};
			}
		}
		function inferredLocationTiers() {
			if (typeof state.lat !== 'number' || typeof state.lng !== 'number') return { country: [], broad: [] };
			var lat = state.lat, lng = state.lng, country = [], broad = [];
			if (lat >= 41 && lat <= 44 && lng >= 39 && lng <= 47) country.push('Georgia');
			if (lat >= 35 && lat <= 48 && lng >= 6 && lng <= 19) country.push('Italy');
			if (lat >= 41 && lat <= 52 && lng >= -6 && lng <= 10) country.push('France');
			if (lat >= 49 && lat <= 61 && lng >= -11 && lng <= 3) country.push('UK');
			if (lat >= 24 && lat <= 50 && lng >= -125 && lng <= -66) country.push('USA');
			if (lat >= -44 && lat <= -10 && lng >= 112 && lng <= 154) country.push('Australia');
			if (lat >= 35 && lat <= 72 && lng >= -25 && lng <= 45) broad.push('Europe');
			if (lat >= 5 && lat <= 55 && lng >= 45 && lng <= 155) broad.push('Asia');
			if (lat >= -56 && lat <= 13 && lng >= -82 && lng <= -34) broad.push('South America');
			return {
				country: country.filter(function(region, index) { return country.indexOf(region) === index; }),
				broad: broad.filter(function(region, index) { return broad.indexOf(region) === index; })
			};
		}
		function updateDetectedLabels() {
			var tiers = inferredLocationTiers();
			state.detectedCountry = tiers.country[0] || '';
			state.detectedBroad = tiers.broad[0] || '';
		}
		function locationPosts(requireMood) {
			refreshDistanceCacheKey();
			return posts.filter(function(post) {
				var hasCoords = typeof post.lat === 'number' && typeof post.lng === 'number';
				return hasCoords && (!requireMood || hasMood(post, state.mood));
			}).map(function(post) {
				var copy = Object.assign({}, post);
				if (typeof state.exactDistanceCache[post.id] !== 'number') {
					state.exactDistanceCache[post.id] = distanceKm(state.lat, state.lng, post.lat, post.lng);
				}
				copy.distance = state.exactDistanceCache[post.id];
				if (post.geoSource && post.geoSource.indexOf('inferred_') === 0) {
					copy.locationNote = 'Roughly ' + Math.round(copy.distance) + ' km from you, estimated from ' + (post.placeLabel || post.regionLabel || 'the article location') + '.';
				}
				return copy;
			}).filter(function(post) {
				return post.distance <= state.radiusKm;
			}).sort(function(a, b) { return a.distance - b.distance; });
		}

		var regionCenters = {
			'Georgia': [42.3154, 43.3569], 'Italy': [42.8333, 12.8333], 'France': [46.2276, 2.2137], 'UK': [54, -2],
			'Spain': [40.4637, -3.7492], 'Germany': [51.1657, 10.4515], 'Iceland': [64.9631, -19.0208], 'Portugal': [39.3999, -8.2245],
			'Norway': [60.472, 8.4689], 'Greece': [39.0742, 21.8243], 'Ireland': [53.1424, -7.6921], 'Turkey': [39.9334, 32.8597],
			'Armenia': [40.0691, 45.0382], 'Azerbaijan': [40.1431, 47.5769], 'Japan': [36.2048, 138.2529], 'China': [35.8617, 104.1954],
			'India': [20.5937, 78.9629], 'Thailand': [15.87, 100.9925], 'Indonesia': [-0.7893, 113.9213], 'Vietnam': [14.0583, 108.2772],
			'Cambodia': [12.5657, 104.991], 'Australia': [-25.2744, 133.7751], 'New Zealand': [-40.9006, 174.886],
			'Brazil': [-14.235, -51.9253], 'Argentina': [-38.4161, -63.6167], 'Chile': [-35.6751, -71.543], 'Peru': [-9.19, -75.0152],
			'USA': [39.8283, -98.5795], 'California': [36.7783, -119.4179], 'Florida': [27.6648, -81.5158], 'Arizona': [34.0489, -111.0937],
			'Texas': [31.9686, -99.9018], 'New York': [43.2994, -74.2179], 'Oregon': [43.8041, -120.5542], 'Nevada': [38.8026, -116.4194],
			'Tennessee': [35.5175, -86.5804], 'Utah': [39.321, -111.0937], 'Alaska': [64.2008, -149.4937], 'Hawaii': [19.8968, -155.5828],
			'Colorado': [39.5501, -105.7821], 'Washington': [47.7511, -120.7401]
		};
		var placeCenters = [
			{ label: 'Tbilisi, Georgia', lat: 41.7151, lng: 44.8271, words: ['tbilisi', 'coffee factory'] },
			{ label: 'Vardzia, Georgia', lat: 41.381, lng: 43.284, words: ['vardzia'] },
			{ label: 'Gergeti Trinity Church, Georgia', lat: 42.6629, lng: 44.6206, words: ['gergeti', 'trinity church', 'kazbegi', 'stepantsminda'] },
			{ label: 'Tusheti, Georgia', lat: 42.37, lng: 45.63, words: ['tusheti', 'omalo', 'road to tusheti'] },
			{ label: 'Svaneti, Georgia', lat: 43.043, lng: 42.729, words: ['svaneti', 'mestia', 'ushguli'] },
			{ label: 'Uplistsikhe, Georgia', lat: 41.967493, lng: 44.20758, words: ['uplistsikhe', 'uplistkhe', 'uplisziche'] },
			{ label: 'Khevsureti, Georgia', lat: 42.52, lng: 44.93, words: ['khevsureti'] },
			{ label: 'Anatori, Georgia', lat: 42.63, lng: 45.16, words: ['anatori'] },
			{ label: 'Batumi, Georgia', lat: 41.6168, lng: 41.6367, words: ['batumi', 'adjara coastline', 'sea slippers'] },
			{ label: 'Adjara, Georgia', lat: 41.65, lng: 42.0, words: ['adjara'] },
			{ label: 'Tsemistskali, Georgia', lat: 41.805, lng: 43.483, words: ['tsemistskali', 'tsagveri', 'eiffel bridge'] },
			{ label: 'Chiatura, Georgia', lat: 42.289, lng: 43.281, words: ['chiatura'] },
			{ label: 'Mtskheta, Georgia', lat: 41.845, lng: 44.718, words: ['mtskheta'] },
			{ label: 'David Gareja, Georgia', lat: 41.447, lng: 45.376, words: ['david gareja', 'gareja'] },
			{ label: 'Saint-Cado Islet, France', lat: 47.68762, lng: -3.18483, words: ['saint-cado', 'saint cado', 'nichtarguer', 'nichtarguér', 'belz'] },
			{ label: 'The Godfather filming locations, Savoca, Sicily', lat: 37.953864, lng: 15.341291, words: ['godfather filming locations', 'the godfather filming locations', 'chiesa di san nicolò', 'chiesa di san nicolo', 'bar vitelli'] },
			{ label: 'St. Roch Cemetery Chapel, New Orleans, USA', lat: 29.97525, lng: -90.05194, words: ['st. roch chapel', 'st roch chapel', 'st. roch cemetery', 'st roch cemetery', 'shrine of st. roch'] },
			{ label: 'Tonga Room & Hurricane Bar, San Francisco, USA', lat: 37.7924, lng: -122.4102, words: ['tonga room', 'hurricane bar', 'fairmont hotel in san francisco'] },
			{ label: 'Savannah, Georgia, USA', lat: 32.0809, lng: -81.0912, words: ['savannah', 'bonaventure', 'forrest gump'] },
			{ label: 'Atlanta, Georgia, USA', lat: 33.749, lng: -84.388, words: ['atlanta'] },
			{ label: 'Helen, Georgia, USA', lat: 34.701, lng: -83.731, words: ['helen georgia', 'helen, georgia'] },
			{ label: 'Providence Canyon, Georgia, USA', lat: 32.064, lng: -84.922, words: ['providence canyon'] },
			{ label: 'Rock City, Georgia, USA', lat: 34.973, lng: -85.349, words: ['rock city'] },
			{ label: 'Georgia Guidestones, USA', lat: 34.111, lng: -82.867, words: ['georgia guidestones', 'guidestones'] }
		];
		function inferredPlaceCenter(post) {
			if (post.placeLabel && typeof post.lat === 'number' && typeof post.lng === 'number') return { label: post.placeLabel, lat: post.lat, lng: post.lng, source: 'exact' };
			var regions = Array.isArray(post.regions) ? post.regions : [];
			var text = String((post.title || '') + ' ' + (post.excerpt || '') + ' ' + regions.join(' ')).toLowerCase();
			for (var i = 0; i < placeCenters.length; i++) {
				for (var j = 0; j < placeCenters[i].words.length; j++) {
					if (text.indexOf(placeCenters[i].words[j]) !== -1) return placeCenters[i];
				}
			}
			return null;
		}
		function approxDistanceForPost(post) {
			refreshDistanceCacheKey();
			if (state.approxCache[post.id]) return state.approxCache[post.id];
			var regions = Array.isArray(post.regions) ? post.regions : [];
			var place = inferredPlaceCenter(post);
			if (place) {
				state.approxCache[post.id] = { label: place.label, distance: distanceKm(state.lat, state.lng, place.lat, place.lng), source: 'place' };
				return state.approxCache[post.id];
			}
			if (state.detectedCountry === 'Georgia' && regions.indexOf('Georgia') !== -1 && regions.indexOf('USA') !== -1) return null;
			for (var i = 0; i < regions.length; i++) {
				if (regionCenters[regions[i]]) {
					var center = regionCenters[regions[i]];
					state.approxCache[post.id] = { label: regions[i], distance: distanceKm(state.lat, state.lng, center[0], center[1]), source: 'region' };
					return state.approxCache[post.id];
				}
			}
			return null;
		}
		function approximatePosts(requireMood) {
			return posts.filter(function(post) {
				if (typeof post.lat === 'number' && typeof post.lng === 'number') return false;
				if (requireMood && !hasMood(post, state.mood)) return false;
				var approx = approxDistanceForPost(post);
				return approx && approx.distance <= state.radiusKm;
			}).map(function(post) {
				var copy = Object.assign({}, post);
				var approx = approxDistanceForPost(post);
				copy.approxDistance = approx.distance;
				copy.locationNote = 'Roughly ' + Math.round(approx.distance) + ' km from you, estimated from ' + approx.label + (approx.source === 'place' ? '.' : ' region.');
				return copy;
			}).sort(function(a, b) { return (a.approxDistance || 999999) - (b.approxDistance || 999999); });
		}
		function preferPrimaryMood(list) {
			if (state.mood === 'Any') return list;
			var primary = list.filter(function(post) { return post.moodLabel === state.mood; });
			return primary.length ? primary : list;
		}
		function displayMood(post) {
			if (state.mood !== 'Any' && hasMood(post, state.mood)) return state.mood;
			return post.moodLabel || ((post.moods || [])[0]) || 'Unusual';
		}
		function firstUnshown(list) {
			for (var i = 0; i < list.length; i++) {
				if (state.shownLocationIds.indexOf(list[i].id) === -1) return list[i];
			}
			return null;
		}
		function remember(post) {
			if (!post) return;
			state.shownLocationIds.push(post.id);
			if (state.shownLocationIds.length > 80) state.shownLocationIds.shift();
		}
		function matchingPosts() {
			return preferPrimaryMood(posts.filter(function(post) { return hasMood(post, state.mood) && hasRegion(post, state.region); }));
		}
		function pickPost(showAnother) {
			if (state.locationMode && typeof state.lat === 'number' && typeof state.lng === 'number') {
				var combined = locationPosts(state.mood !== 'Any').concat(approximatePosts(state.mood !== 'Any'));
				var seen = {};
				combined = combined.filter(function(post) {
					if (seen[post.id]) return false;
					seen[post.id] = true;
					return true;
				}).sort(function(a, b) {
					var ad = typeof a.distance === 'number' ? a.distance : (a.approxDistance || 999999);
					var bd = typeof b.distance === 'number' ? b.distance : (b.approxDistance || 999999);
					return ad - bd;
				});
				combined = preferPrimaryMood(combined);
				if (!combined.length) {
					setStatus((state.mood !== 'Any' ? 'No ' + state.mood + ' articles' : 'No picker articles') + ' found inside ' + state.radiusKm + ' km yet. Increase the radius or choose another mood.');
					return null;
				}
				var picked = showAnother ? firstUnshown(combined) : combined[0];
				if (!picked) {
					state.shownLocationIds = [];
					picked = combined[0];
					setStatus('You have seen the available articles inside ' + state.radiusKm + ' km. Starting again with the nearest one.');
				} else if (showAnother) {
					var d = typeof picked.distance === 'number' ? picked.distance : picked.approxDistance;
					setStatus('Showing another article inside ' + state.radiusKm + ' km' + (typeof d === 'number' ? ', about ' + Math.round(d) + ' km from you.' : '.'));
				}
				remember(picked);
				return picked;
			}
			var matches = matchingPosts();
			if (!matches.length) { setStatus('No exact match yet. Try Anywhere or Any mood.'); return null; }
			if (matches.length > 1 && state.lastId) matches = matches.filter(function(post) { return post.id !== state.lastId; });
			return matches[Math.floor(Math.random() * matches.length)];
		}
		function relatedPosts(main) {
			var related = posts.filter(function(post) {
				if (!main || post.id === main.id) return false;
				var sharedRegion = (post.regions || []).some(function(region) { return (main.regions || []).indexOf(region) !== -1; });
				var sharedMood = (post.moods || []).some(function(mood) { return (main.moods || []).indexOf(mood) !== -1; });
				return sharedRegion || sharedMood;
			});
			return related.sort(function(){ return 0.5 - Math.random(); }).slice(0, 3);
		}
		function imageHtml(post, eager) {
			if (!post.image) return '';
			return '<a href="' + esc(post.url) + '" class="up-spp__image-link"><img src="' + esc(post.image) + '" alt="' + esc(post.title) + '" loading="' + (eager ? 'eager' : 'lazy') + '"></a>';
		}
		function renderPost(post) {
			if (!post) return;
			state.lastId = post.id;
			var distance = post.locationNote ? '<p class="up-spp__distance">' + esc(post.locationNote) + '</p>' : (typeof post.distance === 'number' ? '<p class="up-spp__distance">About ' + Math.round(post.distance) + ' km from you.</p>' : '');
			var related = relatedPosts(post).map(function(item) {
				return '<article class="up-spp__mini">' + imageHtml(item, false) + '<div class="up-spp__mini-body"><p class="up-spp__mini-title"><a href="' + esc(item.url) + '">' + esc(item.title) + '</a></p><p class="up-spp__mini-meta">' + esc(item.regionLabel) + ' · ' + esc(displayMood(item)) + '</p></div></article>';
			}).join('');
			resultsEl.innerHTML = '<article class="up-spp__card up-spp__card--main">' + imageHtml(post, true) + '<div class="up-spp__card-body"><p class="up-spp__meta">' + esc(post.regionLabel) + ' · ' + esc(displayMood(post)) + ' · ' + esc(post.type) + '</p><h3><a href="' + esc(post.url) + '">' + esc(post.title) + '</a></h3>' + distance + '<p>' + esc(post.excerpt) + '</p><p class="up-spp__best">Best for ' + esc(post.bestFor) + '.</p><div class="up-spp__actions"><a class="up-spp__read" href="' + esc(post.url) + '">Read the full article</a><button type="button" class="up-spp__another" data-up-spp-another>Show me another</button></div></div></article>' + (related ? '<div class="up-spp__related"><h3>Related unusual places</h3><div class="up-spp__related-grid">' + related + '</div></div>' : '');
			if (!state.locationMode) setStatus('');
		}
		function choose(showAnother) { renderPost(pickPost(!!showAnother)); }
		function activate(selector, attr, value) {
			root.querySelectorAll(selector).forEach(function(item) {
				var active = item.getAttribute(attr) === value;
				item.classList.toggle('is-active', active);
				item.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
		}
		function setLocationButton(mode) {
			var button = root.querySelector('[data-up-spp-location]');
			if (!button) return;
			button.classList.toggle('is-active', mode === 'active');
			button.classList.toggle('is-checking', mode === 'checking');
			button.setAttribute('aria-busy', mode === 'checking' ? 'true' : 'false');
			button.setAttribute('aria-pressed', mode === 'active' ? 'true' : 'false');
			button.textContent = mode === 'active' ? 'Location on — tap to turn off' : (mode === 'checking' ? 'Checking location...' : 'Use my location');
		}
		function disableLocationMode(message) {
			state.locationMode = false;
			state.lat = null;
			state.lng = null;
			state.detectedCountry = '';
			state.detectedBroad = '';
			state.shownLocationIds = [];
			state.exactDistanceCache = {};
			state.approxCache = {};
			state.distanceCacheKey = '';
			setLocationButton('off');
			if (message) setStatus(message);
		}
		function enableLocationMode(position) {
			state.lat = position.coords.latitude;
			state.lng = position.coords.longitude;
			state.locationMode = true;
			state.region = 'Anywhere';
			state.shownLocationIds = [];
			state.exactDistanceCache = {};
			state.approxCache = {};
			state.distanceCacheKey = '';
			updateDetectedLabels();
			setLocationButton('active');
			activate('[data-region-group]', 'data-region-group', 'Anywhere');
			renderCountries('Anywhere');
			setStatus('Location on' + (state.detectedCountry ? ' in ' + state.detectedCountry : '') + '. Searching within ' + state.radiusKm + ' km first.');
			choose(false);
		}
		function locationErrorMessage(error) {
			if (error && error.code === 1) return 'Location permission was denied. On iPhone, allow Safari Websites to use Location Services, then tap again.';
			if (error && error.code === 3) return 'Location timed out. On mobile, make sure browser location is allowed and try again.';
			return 'Location could not be detected on this device. Try again or choose a region manually.';
		}
		function requestBrowserLocation() {
			if (!navigator.geolocation) {
				setStatus('Your browser does not support location search. Try choosing a region instead.');
				return;
			}
			if (window.isSecureContext === false) {
				setStatus('Location needs HTTPS. Open the live secure page, or choose a region manually.');
				return;
			}
			var settled = false;
			var watchId = null;
			var finish = function(position) {
				if (settled) return;
				settled = true;
				if (watchId !== null) navigator.geolocation.clearWatch(watchId);
				enableLocationMode(position);
			};
			var fail = function(error) {
				if (settled) return;
				settled = true;
				if (watchId !== null) navigator.geolocation.clearWatch(watchId);
				disableLocationMode('');
				setStatus(locationErrorMessage(error));
			};
			setLocationButton('checking');
			setStatus('Checking your browser location...');
			try {
				watchId = navigator.geolocation.watchPosition(finish, function(){}, { enableHighAccuracy: false, timeout: 22000, maximumAge: 600000 });
			} catch (ignoreWatchError) {}
			navigator.geolocation.getCurrentPosition(finish, function(firstError) {
				if (settled) return;
				setLocationButton('checking');
				setStatus('Still checking. If prompted, allow location access for this browser.');
				navigator.geolocation.getCurrentPosition(finish, function(secondError) {
					fail(secondError || firstError);
				}, { enableHighAccuracy: true, timeout: 22000, maximumAge: 0 });
			}, { enableHighAccuracy: false, timeout: 15000, maximumAge: 600000 });
		}
		function setManualRegion(region) {
			state.region = region || 'Anywhere';
			disableLocationMode('');
			setStatus('');
		}
		function renderCountries(groupName) {
			var group = regionGroups[groupName] || {};
			var countries = Array.isArray(group.countries) ? group.countries : [];
			if (!countryWrap || !countryList) return;
			countryList.innerHTML = '';
			if (!countries.length || groupName === 'Anywhere') {
				countryWrap.hidden = true;
				return;
			}
			countryWrap.hidden = false;
			if (countryLabel) countryLabel.textContent = groupName === 'USA' ? 'Available states in USA' : 'Available countries in ' + groupName;
			countries.forEach(function(country) {
				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'up-spp__chip';
				button.setAttribute('data-country-region', country);
				button.setAttribute('aria-pressed', state.region === country ? 'true' : 'false');
				if (state.region === country) button.classList.add('is-active');
				button.textContent = country;
				countryList.appendChild(button);
			});
		}
		function updateRadiusLabel() {
			if (!radiusInput) return;
			state.radiusKm = Number(radiusInput.value || 250);
			if (radiusLabel) radiusLabel.textContent = state.radiusKm + ' km';
			root.querySelectorAll('[data-up-spp-radius-preset]').forEach(function(button) {
				button.classList.toggle('is-active', Number(button.getAttribute('data-up-spp-radius-preset')) === state.radiusKm);
			});
		}

		updateRadiusLabel();
		root.addEventListener('click', function(event) {
			var moodButton = event.target.closest('[data-mood]');
			var regionGroupButton = event.target.closest('[data-region-group]');
			var countryButton = event.target.closest('[data-country-region]');
			var locationButton = event.target.closest('[data-up-spp-location]');
			var radiusPreset = event.target.closest('[data-up-spp-radius-preset]');
			var findButton = event.target.closest('[data-up-spp-find], [data-up-spp-another]');

			if (moodButton && root.contains(moodButton)) {
				state.mood = moodButton.getAttribute('data-mood') || 'Any';
				state.shownLocationIds = [];
				activate('[data-mood]', 'data-mood', state.mood);
				if (state.locationMode) choose(false);
			}
			if (regionGroupButton && root.contains(regionGroupButton)) {
				var groupName = regionGroupButton.getAttribute('data-region-group') || 'Anywhere';
				setManualRegion(regionGroupButton.getAttribute('data-region') || groupName);
				activate('[data-region-group]', 'data-region-group', groupName);
				renderCountries(groupName);
			}
			if (countryButton && root.contains(countryButton)) {
				setManualRegion(countryButton.getAttribute('data-country-region') || 'Anywhere');
				root.querySelectorAll('[data-country-region]').forEach(function(item) {
					var active = item.getAttribute('data-country-region') === state.region;
					item.classList.toggle('is-active', active);
					item.setAttribute('aria-pressed', active ? 'true' : 'false');
				});
			}
			if (locationButton && root.contains(locationButton)) {
				if (state.locationMode) {
					disableLocationMode('Location turned off. Manual region filters are active again.');
					choose(false);
				} else {
					requestBrowserLocation();
				}
			}
			if (radiusPreset && root.contains(radiusPreset) && radiusInput) {
				radiusInput.value = radiusPreset.getAttribute('data-up-spp-radius-preset');
				updateRadiusLabel();
				state.shownLocationIds = [];
				if (state.locationMode) choose(false);
			}
			if (findButton && root.contains(findButton)) choose(!!event.target.closest('[data-up-spp-another]'));
		});
		if (radiusInput) {
			radiusInput.addEventListener('input', function() {
				updateRadiusLabel();
				state.shownLocationIds = [];
				if (state.locationMode) {
					window.clearTimeout(root._upSppRadiusTimer);
					root._upSppRadiusTimer = window.setTimeout(function() { choose(false); }, 180);
				}
			});
			radiusInput.addEventListener('change', function() {
				updateRadiusLabel();
				state.shownLocationIds = [];
				if (state.locationMode) choose(false);
			});
		}
	});
})();
