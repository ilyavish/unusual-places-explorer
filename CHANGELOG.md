# Changelog

## 1.1.2 - 2026-05-05

- Add named coordinates for St. Roch Cemetery Chapel in New Orleans.
- Add named coordinates for The Godfather filming locations in Sicily and Tonga Room in San Francisco.
- Decode public post titles and excerpts before output so entities like `&#8220;`, `&#8217;`, and `&#038;` display as readable punctuation.

## 1.1.1 - 2026-05-05

- Add article/place-specific coordinate inference for Saint-Cado, Uplistsikhe, Georgia articles, France articles, and other common archive places.
- Force a fresh picker cache so old broad country-center coordinates are replaced on rebuild.
- Improve nearby sorting by preferring named place matches before region or country centers.

## 1.1.0 - 2026-05-05

- Store inferred moods, regions, type, and approximate article coordinates during cache rebuilds and post saves.
- Prefer exact/manual coordinates over inferred coordinates.
- Add conservative place and region inference so location mode can sort more nearby articles without relying on the old XML export.
- Display inferred coordinate and classification details in the post editor meta box.
- Hide the mobile location troubleshooting note on desktop.

## 1.0.0 - 2026-05-05

- Initial WordPress plugin version of The Strange Place Picker.
- Added shortcode, live post indexing, monthly rebuilds, admin rebuild button, mobile-first picker UI, and client-side location sorting.
