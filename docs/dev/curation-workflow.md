# Curation workflow implementation

Migration `0048_curation_workflow.sql` adds tenant `editor` and `user` roles, paid-plan workflow capability, curation lists/items, and user messages. `CurationRepository` enforces tenant scoping. Editors are admitted only to `/admin/curation`; owner/admin access remains required for other tenant-admin routes. Public artwork reads accept an explicit authenticated-preview flag.

<!-- End of file. -->
