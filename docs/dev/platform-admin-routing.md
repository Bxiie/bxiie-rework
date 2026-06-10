# Platform admin canonical host routing

Platform-admin routes live on `https://artsfol.io/platform/admin` and must not be served through tenant hosts.
Tenant hosts such as `bxiie.artsfol.io` redirect `/platform/admin...` requests to the canonical platform host while preserving the path and query string.

This avoids tenant-router 404 pages for platform URLs and keeps platform-admin cookies, branding, and access-control context isolated from tenant public sites.

# End of file.
