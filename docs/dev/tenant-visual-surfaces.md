# Tenant visual surface implementation

Tenant visual settings are stored as key/value rows through `TenantSettingsRepository`; no schema migration is required for new visual keys.

Public tenant pages emit CSS variables from `App\Http\Controllers\Tenant\HomeController::tenantSurfaceCssVariables()`.
Tenant admin pages emit matching variables from `App\Http\View\TenantAdminLayout::tenantSurfaceCssVariables()`.

The variables include header shadow, text color, heading/content/text overlay colors, menu/topbar imagery, and artwork card background controls. Overlay variables are precomputed where possible so opacity affects the background spread instead of fading text.

The retired delayed signup prompt remains as an inert JavaScript function in `public/assets/tenant-forms.js` to avoid errors if cached markup references it, but it no longer opens a modal or prompt.

# End of file.
