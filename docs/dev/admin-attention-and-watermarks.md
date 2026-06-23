# Admin attention and watermark implementation

Navigation badges are computed in App\Http\View\TenantAdminNav and App\Http\View\AdminLayout. Tenant email-signup view timestamps are persisted in tenant_settings; the platform timestamp is stored in platform_settings. Public media watermarking is performed at response time by App\Tenant\Media\WatermarkService and App\Http\Controllers\Tenant\MediaController. The shared public platform menu is App\Http\View\PlatformChrome::topNavigation().

<!-- End of file. -->
