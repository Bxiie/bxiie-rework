# Admin UI and watermark runtime

`public/assets/admin-table-tools.js` progressively enhances `.admin-table` elements with current-page filtering and sortable headers. Server-side query controls remain the source of truth.

`App\Tenant\Media\WatermarkService` reads tenant settings and renders an opt-in GD watermark in `MediaController` for public, published artwork variants except thumbnails. The response cache lifetime is one day so appearance changes propagate without rewriting stored variants.

`OperationsMonitorRepository::searchRuns()` and `metricHistoryRange()` provide bound date/status queries used by the operations console.

<!-- End of file. -->
