<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- elasticsearch/elasticsearch - v8
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- predis/predis - v3
- vladimir-yuldashev/laravel-queue-rabbitmq - v15
- @inertiajs/vue3 (INERTIA_VUE) - v3
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Local Runtime & Containers

- This repository uses a custom `docker compose` stack from `compose.yaml` as the primary local runtime.
- Prefer running project commands inside the `php` service/container unless there is a specific reason to run them on the host.
- Main services in the stack:
  - `php` - application container, working directory `/var/www/html`
  - `nginx` - Nginx frontend with SSL termination
  - `pxc-node1` / `pxc-node2` / `pxc-node3` - Percona XtraDB Cluster 8.0 (persistent volumes on all three; `pxc-node1` force-bootstraps after unclean full-cluster stops via `docker/pxc-node1-entrypoint.sh`)
  - `proxysql` - SQL proxy in front of PXC (app hostname `mariadb`)
  - `redis` - sessions and Invoice Pulse (engagement ranking)
  - `rabbitmq` - application queues (`QUEUE_CONNECTION=rabbitmq`; management UI on host port 15672)
  - `memcached` - application cache (`CACHE_STORE=memcached`)
  - `elasticsearch` - invoice full-text / prefix search (no host port published; Docker network only)
  - `mailhog` - local mail inbox UI
- Host ports:
  - app HTTP: `http://localhost:8080`
  - app HTTPS: `https://localhost:8443`
  - Vite dev server: `http://localhost:5173` (container also exposes HTTPS Vite on 5173 in local setup)
  - MailHog UI: `http://localhost:8025`
  - RabbitMQ AMQP: `localhost:5672`
  - RabbitMQ management UI: `http://localhost:15672` (user/pass `laravel` / `laravel`)
- Internal container hostnames used by the app configuration:
  - database: `mariadb` (ProxySQL)
  - redis: `redis`
  - rabbitmq: `rabbitmq` (`RABBITMQ_HOST=rabbitmq`)
  - memcached: `memcached`
  - elasticsearch: `elasticsearch` (`ELASTICSEARCH_HOST=http://elasticsearch:9200`)
  - mail: `mailhog`
  - PHP FPM hostname behind Nginx: `php-fpm`
  - Nginx hostname: `nginx`
- The app's `.env` / `.env.example` is configured for container networking (`DB_HOST=mariadb`, `REDIS_HOST=redis`, `RABBITMQ_HOST=rabbitmq`, `MEMCACHED_HOST=memcached`, `MAIL_HOST=mailhog`, `ELASTICSEARCH_HOST=http://elasticsearch:9200`), so preserve container-first assumptions when troubleshooting.
- Queue workers and Reverb are not automatically managed by this compose file for local development; start them explicitly when needed.
- After first boot (or mapping changes), rebuild the invoices search index with `php artisan invoices:reindex --fresh`.
- Local Elasticsearch disables disk allocation watermarks in `compose.yaml` so a nearly-full host disk does not leave the cluster red / hang index operations.

## Preferred Container Command Patterns

- Prefer `docker compose exec php ...` for PHP / Composer / Artisan work.
- Laravel Boost MCP in Cursor runs through `.cursor/boost-mcp.sh`, which executes `boost:mcp` inside the `php` container. Keep the Docker stack running before using Boost MCP tools.
- Prefer `docker compose exec php npm ...` for frontend package scripts when working inside the application container.
- Use `docker compose up -d --build` to build and start the local stack.
- Use `docker compose ps` to inspect service state.
- Use `docker compose logs <service>` when debugging container startup or runtime issues.
- Prefer ProxySQL / app-level queries; for cluster SQL debugging use `docker compose exec proxysql` or a PXC node when needed.
- Use `docker compose exec redis redis-cli` for Redis inspection when needed.
- Use `docker compose exec rabbitmq rabbitmq-diagnostics -q ping` (or open `http://localhost:15672`) to inspect RabbitMQ when needed.
- Use `docker compose exec php curl -sS http://elasticsearch:9200/_cluster/health?pretty` to inspect Elasticsearch when needed.
- The repository includes a `sail` wrapper script, but this project is not using a stock Sail service layout. Treat direct `docker compose` commands as the safer default unless the wrapper is already known to be configured correctly for the current environment.

## Local Workflow Notes

- First-time local setup is container-oriented: bring up the stack, then run install / key generation / migrations / `invoices:reindex --fresh` / asset build inside the app container.
- If frontend changes are not visible, check whether Vite is running in the container or whether a production build is needed.
- Nginx terminates HTTPS on `8443`; WebSocket traffic for Reverb is proxied through Nginx to the PHP container. PHP-FPM pool settings live in `docker/php-fpm.conf` (mounted as `zz-app.conf`).
- Mail delivery in local development should be verified through MailHog, not a real SMTP provider.
- When sharing or testing URLs locally, prefer the exposed host ports above rather than internal container addresses unless a command runs entirely inside the Docker network.
- Invoice search depends on Elasticsearch; Invoice Pulse and sessions depend on Redis; queues depend on RabbitMQ; dashboard cache depends on Memcached.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

## Cursor Cloud specific instructions

This environment runs the app via the repo's Docker Compose stack (see the "Local Runtime & Containers" section above for services/ports). The following are non-obvious startup/run caveats discovered while setting up the cloud VM. Standard commands live in `compose.yaml`, `composer.json`, `package.json`, and the README — reference those rather than duplicating.

### Starting things up (not handled by the update script)
- Docker requires `sudo` here, and the daemon is not auto-started. Start it once per VM boot in a persistent shell (e.g. tmux): `sudo dockerd`. The daemon config at `/etc/docker/daemon.json` is already set for this VM (Docker 29 needs `storage-driver: fuse-overlayfs` **and** `features.containerd-snapshotter: false`; iptables is set to `iptables-legacy`).
- Bring up the stack with `sudo docker compose up -d` (images are already built in the VM snapshot, so this is fast; use `--build` only after changing anything under `docker/`).
- App code, `vendor/`, `node_modules/`, `public/build/`, and `.env` live on the host via the bind mount, so they persist across VM restarts — you normally do NOT need to reinstall dependencies.

### PHP / Composer gotcha (important)
- Inside the `php` container there are two PHP binaries. `/usr/local/bin/php` (the official image PHP, used by `php artisan` and `vendor/bin/*`) has all required extensions. The `composer` wrapper, however, calls the apk `php85` build which is **missing `session` and `tokenizer`**, so `composer install` fails with platform-requirement errors. Run Composer through the official PHP instead: `php /usr/bin/composer.phar install` (same for any composer command).

### Xdebug noise / slowness
- The image ships Xdebug in step-debug mode (`docker/php.ini`). Every PHP CLI invocation prints `Xdebug: [Step Debug] Could not connect to debugging client...` and runs much slower (PHPStan/tests can take minutes). Prefix CLI commands with `XDEBUG_MODE=off` for clean, fast runs, e.g. `XDEBUG_MODE=off php artisan test`, `XDEBUG_MODE=off vendor/bin/phpstan analyse`.

### Dev servers (start manually inside the `php` container)
- Frontend assets: `npm run dev` (Vite HMR on port 5173). Without it you must have a `npm run build` output or you'll hit a Vite manifest error. `npm run dev` creates `public/hot`; delete it to fall back to built assets.
- Jobs: `php artisan queue:work` (or `php artisan rabbitmq:consume`) is required for PDF generation, email sending, and paid-invoice notifications (queue is RabbitMQ-backed and not started by compose).
- Reverb (`php artisan reverb:start`) is optional; without it the browser console/network will show harmless 503s from Echo trying to reach the WebSocket endpoint.

### HTTPS / browser access
- The app forces HTTPS: use `https://localhost:8443` (port 8080 just 301-redirects to 8443). Both the app cert (8443) and the Vite dev-server cert (5173) are self-signed, so a browser must accept both certificates (visit `https://localhost:5173` once and proceed, then the app) before Inertia assets will load in dev mode.
- Sent mail (e.g. Fortify email verification) is captured by MailHog at `http://localhost:8025`.

### Testing
- The PHPUnit suite uses in-memory SQLite (see `phpunit.xml`) for the database and needs no DB services.
- Feature tests for Invoice Pulse / Invoice Search skip when Redis or Elasticsearch are unreachable; keep those compose services up to exercise them (`REDIS_*` and `ELASTICSEARCH_*` are set in `phpunit.xml` for the Docker network).
- `XDEBUG_MODE=off php artisan test --compact` is the preferred invocation.
- Feature tests that render Inertia pages require `public/build/manifest.json`, so run `npm run build` once before running the suite (or keep `npm run dev` running).
