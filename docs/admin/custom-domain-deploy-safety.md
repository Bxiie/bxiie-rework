# Custom-domain deploy safety

Tenant custom domains are persistent production configuration. Deployment and preflight checks should never remove or disable them.

For the Bxiie tenant, production should contain active domain mappings for:

- `bxiie.artsfol.io`
- `bxiie.com`
- `www.bxiie.com`

If a custom domain renders platform content, check Platform Admin -> Domains and confirm the hostname is active and assigned to the expected tenant. The deploy migration preserves the Bxiie mappings, but it will not steal a hostname that is already assigned to another tenant.

# End of file.
