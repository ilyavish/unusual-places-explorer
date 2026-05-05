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

## Mobile Location Notes

Visitor coordinates stay client-side in the browser. The plugin does not save visitor location, use cookies, or call third-party APIs.

For best nearby results, add exact article coordinates in the post editor box:

- Place label
- Latitude
- Longitude

The plugin can infer approximate article locations from titles, categories, tags, and known place clues, but exact coordinates are still better for precise nearby sorting.
