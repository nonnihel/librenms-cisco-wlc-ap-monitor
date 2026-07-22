# GitHub publishing and release workflow

## Repository contents

The repository is intended to contain only reusable plugin source and documentation. Do not commit:

- SNMP community strings
- passwords, tokens, private keys, or API secrets
- internal DNS names or private IP-address inventories
- LibreNMS database dumps
- production logs
- screenshots containing confidential infrastructure data
- generated `vendor/` contents

## Clone for development

```bash
git clone https://github.com/nonnihel/librenms-cisco-wlc-ap-monitor.git
cd librenms-cisco-wlc-ap-monitor
```

## Test before committing

Lint PHP files:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Validate Composer metadata:

```bash
composer validate --no-check-lock
```

Test the installer on a non-production LibreNMS instance:

```bash
sudo bash install.sh
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/healthcheck.sh
```

## Commit changes

```bash
git checkout -b feature/my-change
git add .
git commit -m "Describe the change"
git push -u origin feature/my-change
```

Open a pull request into `main` and wait for the PHP lint workflow to pass.

## Create a release

1. Update `VERSION`.
2. Add release notes to `CHANGELOG.md`.
3. Test installation and update paths.
4. Merge the release changes into `main`.
5. Create a Git tag such as `v1.2.0`.
6. Create a GitHub Release from the tag.
7. Attach a ZIP archive only when a prebuilt release archive is useful; GitHub automatically provides source ZIP and tarball downloads.

Example:

```bash
git tag -a v1.2.0 -m "Cisco WLC AP Monitor 1.2.0"
git push origin v1.2.0
```

## Suggested repository topics

Add these topics in the GitHub repository settings:

```text
librenms
cisco
cisco-wlc
wireless
access-point
network-monitoring
alerting
php
```
