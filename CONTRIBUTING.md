# Contributing

1. Create a branch from `main`.
2. Keep LibreNMS core files untouched.
3. Run `php -l` against every PHP file.
4. Test installation, update, polling, service status, routes, dashboard widget, and GUI actions on a non-production LibreNMS instance.
5. Open a pull request describing the LibreNMS, PHP, database, and Cisco WLC versions used for testing.

Please avoid committing credentials, SNMP communities, internal hostnames, database dumps, or production logs.
