# Identity and Membership

## Authentication models

ArtsFolio supports:

```text
OAuth/OIDC
Local email/password
```

APIs use:

```text
OAuth2 bearer tokens
```

## Core model

```text
global user
many auth identities
many tenant memberships
many role assignments
```

Users are global. Tenants do not own users.

## Manual verification

```bash
php scripts/database/migrate.php
docker exec -i artsfolio-mariadb mariadb -u artsfolio -partsfolio_dev artsfolio < database/seeds/0003_roles.sql
php scripts/test/identity_membership.php
php scripts/test/auth_architecture.php
```

<!-- End of file. -->
