# Unusual Places Explorer

WordPress plugin for the `unusualplaces.org` Strange Place Picker.

## Goal

Help visitors discover published Unusual Places articles by mood, region, or browser location, while sending every result deeper into the existing article archive.

## Shortcode

```text
[up_strange_place_picker]
```

## Install

1. Download or zip the `unusual-places-explorer` plugin folder.
2. In WordPress admin, go to Plugins > Add New > Upload Plugin.
3. Upload and activate the zip.
4. Add `[up_strange_place_picker]` to the Strange Place Picker page.
5. Go to Settings > Unusual Places Explorer and click “Rebuild picker cache now.”

## How New Articles Are Picked Up

The plugin reads live published WordPress posts with `WP_Query`.

It rebuilds the picker index:

- once a month by WP-Cron,
- whenever a published post is saved/updated,
- manually from Settings > Unusual Places Explorer.

No XML dump is needed after the plugin is installed.

During rebuilds and post saves, the plugin also stores inferred picker metadata on each post:

- moods,
- regions,
- type,
- approximate article coordinates when exact coordinates are missing.

Exact coordinates entered in the post editor always override inferred coordinates.

## Explorer Place Data

Each post has an **Unusual Places Explorer Data** box with optional structured fields:

- Explorer Record Type: Auto / Unclassified, Single Place, Multi-place / List, or Non-place
- Picker Inclusion: Auto, Include, or Exclude
- Existing exact place label, latitude, and longitude fields
- Manual Place Type override
- Cost, Opening Status, Dog Friendly, Environment, and Last Verified

Existing posts remain Auto / Unclassified and Auto-included, so upgrading does not require an immediate content migration. Manual exact coordinates and manual Place Type override inferred values. Last Verified changes only when an editor changes it.

The new post-meta keys are `_up_spp_record_type`, `_up_spp_inclusion`, `_up_spp_manual_place_type`, `_up_spp_cost`, `_up_spp_opening_status`, `_up_spp_dog_friendly`, `_up_spp_environment`, and `_up_spp_last_verified`. Existing `_up_spp_lat`, `_up_spp_lng`, `_up_spp_place_label`, and `_up_spp_inferred_*` data is reused unchanged.

Under **Settings > Unusual Places Explorer**, administrators can exclude whole WordPress categories. Per-post Include overrides category exclusion; per-post Exclude always wins.

## Performance

The shortcode keeps a small crawlable fallback selection in its HTML and loads the full cached index from a cacheable WordPress REST endpoint. Post saves retain the current index for visitors and schedule a background rebuild, avoiding a full archive rebuild during a frontend request.

## Mobile Location Notes

Visitor coordinates stay client-side in the browser. The plugin does not save visitor location, use cookies, or call third-party APIs.

For best nearby results, add exact article coordinates in the post editor box:

- Place label
- Latitude
- Longitude

The plugin can infer approximate article locations from titles, categories, tags, and known place clues, but exact coordinates are still better for precise nearby sorting.

When a place is known by name, the picker uses that named-place coordinate before falling back to country or region centers. If a result still appears in the wrong place, add exact coordinates in the post editor and rebuild the cache.
