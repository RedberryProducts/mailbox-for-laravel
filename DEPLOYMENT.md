# Running Mailbox in Containers

This guide covers running Mailbox for Laravel on Docker, Docker Compose, Kubernetes, and other container platforms (ECS, Cloud Run, Fly.io, etc.). It's for staging servers, review apps, and shared QA environments where captured mail has to survive restarts and be visible from every replica.

## Why the defaults don't work in containers

With zero configuration, everything Mailbox stores lives on the container's own filesystem:

| What | Default location |
|---|---|
| Messages (`sqlite` driver) | `storage/app/mailbox/mailbox.sqlite` |
| Attachment content (`mailbox` disk) | `storage/app/mailbox/attachments/` |

That works on a laptop but breaks in containers:

- **Restarts wipe the inbox.** A redeploy, crash, or reschedule starts a fresh container with an empty `storage/`.
- **Each replica gets its own inbox.** An email captured by pod A won't show up on the dashboard when the load balancer sends you to pod B.
- **Queue workers capture into their own container.** If you queue mail, the worker writes the message, not the web container that serves the dashboard.

Every container that sends mail or serves the dashboard (web, queue workers, scheduler) must point at the **same** storage. There are two ways to do that:

| | [Option A: Database driver](#option-a-database-driver-recommended) | [Option B: File driver on a shared volume](#option-b-file-driver-on-a-shared-volume) |
|---|---|---|
| Messages stored in | Your MySQL / Postgres / MariaDB / SQL Server | JSON files on a shared volume |
| Attachments stored on | S3 (or any shared disk) | The same shared volume, or S3 |
| Needs migrations | Yes, once | No |
| Needs a ReadWriteMany volume | No | Yes |
| Scales with many messages | Well (indexed queries) | Poorly (every list reads every file) |
| Best for | Most setups, Kubernetes with several replicas | Platforms with an easy shared filesystem and no spare database |

## Prerequisites (both options)

### 1. Install the package as a regular dependency

The README suggests `composer require --dev`, but most images are built with `composer install --no-dev`, which leaves the package out. Images for environments that run the inbox need it as a regular dependency:

```bash
composer require redberry/mailbox-for-laravel
```

The master switch `MAILBOX_ENABLED` (on everywhere except `APP_ENV=production`) keeps it inert where you don't want it.

### 2. Bake the dashboard assets into the image

The dashboard's compiled JS/CSS has to exist at `public/vendor/mailbox` in the image. Either commit the published assets, or publish them during the build:

```dockerfile
RUN composer install --no-dev --optimize-autoloader \
 && php artisan vendor:publish --tag=mailbox-assets --force
```

If a separate web server container (e.g. an nginx sidecar) serves your static files, it needs `public/vendor/mailbox` too. Copy it into that image from the same build stage.

> Don't rely on `php artisan mailbox:install` at container start to publish assets. It writes into `public/`, which is read-only or discarded in most container setups, and it only publishes into the container that runs it.

### 3. Point the mailer at Mailbox

```env
MAIL_MAILER=mailbox
# Optional: also deliver for real through an existing mailer
# MAILBOX_DECORATE=smtp
```

### 4. Define the `viewMailbox` gate

The default gate only allows `APP_ENV=local`. In any other environment, every dashboard request is denied until you define your own gate. Captured mail contains password reset links and login tokens, so keep the gate strict:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewMailbox', fn ($user) => $user?->isAdmin());
}
```

To send guests to your login page instead of returning a 403, set `MAILBOX_UNAUTHORIZED_REDIRECT=/login`.

If the container runs with `APP_ENV=production` (some teams do this on staging), set `MAILBOX_ENABLED=true` explicitly.

### 5. Give every container the same configuration

Web, queue worker, and scheduler containers all need the same `MAIL_MAILER` and `MAILBOX_*` variables and access to the same storage. If you run `php artisan config:cache`, run it at container start (after the environment is injected), not at image build time. Otherwise the cached config keeps whatever environment the build had.

---

## Option A: Database driver (recommended)

Messages and attachment metadata go to a real database server. Attachment bytes go to object storage. The containers stay stateless.

### A1. Choose a connection

**Reuse your app's database.** This is the simplest choice. The tables are prefixed `mailbox_`, so they won't collide with yours:

```env
MAILBOX_STORE_DRIVER=database
MAILBOX_STORE_DATABASE_CONNECTION=pgsql   # or mysql, mariadb, sqlsrv
```

**Or use a dedicated database** to keep captured mail out of your app's backups and dumps. Add the connection to `config/database.php`:

```php
'connections' => [
    // ...
    'mailbox' => [
        'driver' => 'pgsql',
        'host' => env('MAILBOX_DB_HOST'),
        'port' => env('MAILBOX_DB_PORT', '5432'),
        'database' => env('MAILBOX_DB_DATABASE', 'mailbox'),
        'username' => env('MAILBOX_DB_USERNAME'),
        'password' => env('MAILBOX_DB_PASSWORD'),
        'charset' => 'utf8',
        'prefix' => '',
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ],
],
```

```env
MAILBOX_STORE_DRIVER=database
MAILBOX_STORE_DATABASE_CONNECTION=mailbox
```

> **Gotcha:** if the connection named in `MAILBOX_STORE_DATABASE_CONNECTION` is not defined in `config/database.php`, the package silently creates it as a **local SQLite file** inside the container. Everything appears to work until the next restart. Check it with the [verification](#verifying-the-setup) step below.

### A2. Store attachment content on shared storage

Attachment metadata lives in the database, but the file bytes always go to the `mailbox` filesystem disk, which is a local folder by default. Define the disk yourself and the package will use yours:

```php
// config/filesystems.php
'disks' => [
    // ...
    'mailbox' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('MAILBOX_S3_REGION', env('AWS_DEFAULT_REGION')),
        'bucket' => env('MAILBOX_S3_BUCKET'),
        'endpoint' => env('AWS_ENDPOINT'),            // MinIO, R2, DigitalOcean Spaces...
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'visibility' => 'private',
        'throw' => false,
    ],
],
```

The S3 driver needs `league/flysystem-aws-s3-v3`. Keep the bucket private: the dashboard streams attachments through its own gated routes.

If you don't need attachments at all, skip this step and set `MAILBOX_ATTACHMENTS_ENABLED=false`.

### A3. Create the tables

Run the package migrations once per environment, as a release step and not on every container start. Use one of these:

```bash
# Simple: also publishes config and assets into the container that runs it (harmless there)
php artisan mailbox:install

# Migrations only: use this on a read-only filesystem
php artisan migrate --force \
  --database="${MAILBOX_STORE_DATABASE_CONNECTION}" \
  --path=vendor/redberry/mailbox-for-laravel/database/migrations
```

The migrations target the configured mailbox connection, so a plain `php artisan migrate` for your app does **not** create these tables.

Never pass `--refresh` to `mailbox:install` in a release step. It drops the tables.

### A4. Example: Docker Compose

```yaml
x-app-env: &app-env
  APP_ENV: staging
  DB_CONNECTION: pgsql
  DB_HOST: postgres
  DB_DATABASE: app
  DB_USERNAME: app
  DB_PASSWORD: secret
  MAIL_MAILER: mailbox
  MAILBOX_STORE_DRIVER: database
  MAILBOX_STORE_DATABASE_CONNECTION: pgsql
  MAILBOX_S3_BUCKET: mailbox
  AWS_ACCESS_KEY_ID: minio
  AWS_SECRET_ACCESS_KEY: minio-secret
  AWS_DEFAULT_REGION: us-east-1
  AWS_ENDPOINT: http://minio:9000
  AWS_USE_PATH_STYLE_ENDPOINT: "true"

services:
  migrate:
    image: my-app:latest
    environment: *app-env
    command: php artisan mailbox:install
    depends_on: [postgres]
    restart: "no"

  app:
    image: my-app:latest
    environment: *app-env
    depends_on:
      migrate:
        condition: service_completed_successfully

  worker:
    image: my-app:latest
    environment: *app-env
    command: php artisan queue:work
    depends_on:
      migrate:
        condition: service_completed_successfully

  postgres:
    image: postgres:17
    environment:
      POSTGRES_DB: app
      POSTGRES_USER: app
      POSTGRES_PASSWORD: secret
    volumes: [pgdata:/var/lib/postgresql/data]

  minio:
    image: minio/minio
    command: server /data
    environment:
      MINIO_ROOT_USER: minio
      MINIO_ROOT_PASSWORD: minio-secret
    volumes: [miniodata:/data]

volumes:
  pgdata:
  miniodata:
```

Create the `mailbox` bucket in MinIO once (with `mc mb` or the console).

### A5. Example: Kubernetes

Shared configuration:

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: app-mailbox
data:
  MAIL_MAILER: mailbox
  MAILBOX_STORE_DRIVER: database
  MAILBOX_STORE_DATABASE_CONNECTION: pgsql
  MAILBOX_S3_BUCKET: my-staging-mailbox
  MAILBOX_RETENTION_SCHEDULE: "true"
```

Migrations as a Job (or a Helm `pre-upgrade` hook / Argo CD `PreSync` hook):

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: app-mailbox-migrate
spec:
  backoffLimit: 2
  template:
    spec:
      restartPolicy: Never
      containers:
        - name: migrate
          image: registry.example.com/my-app:1.4.0
          command: ["php", "artisan", "mailbox:install"]
          envFrom:
            - configMapRef: { name: app-mailbox }
            - secretRef: { name: app-secrets }   # DB + AWS credentials
```

Web and worker Deployments load the same `envFrom` and need no volumes:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app-web
spec:
  replicas: 3
  selector: { matchLabels: { app: app-web } }
  template:
    metadata: { labels: { app: app-web } }
    spec:
      containers:
        - name: web
          image: registry.example.com/my-app:1.4.0
          envFrom:
            - configMapRef: { name: app-mailbox }
            - secretRef: { name: app-secrets }
```

On EKS, use IRSA (or Pod Identity) instead of static AWS keys, and leave `key`/`secret` unset in the disk config.

---

## Option B: File driver on a shared volume

Messages are stored as JSON files, attachment metadata as JSON sidecars, and attachment bytes as plain files, all under one directory. Mount a shared volume there and every container sees the same inbox. No database or migrations are needed.

With the default paths, everything lives under `storage/app/mailbox/`:

```
storage/app/mailbox/
├── <message-id>.json        # one file per captured message
├── attachments-index/       # attachment metadata sidecars
└── attachments/             # attachment content (the "mailbox" disk)
```

So **one volume mounted at `storage/app/mailbox`** covers everything.

### B1. Configure

```env
MAILBOX_STORE_DRIVER=file
# Default is storage_path('app/mailbox'). Set it only if you mount the volume elsewhere:
# MAILBOX_STORE_FILE_PATH=/mnt/mailbox
```

If you change `MAILBOX_STORE_FILE_PATH`, also move the attachment content onto the volume by defining the `mailbox` disk:

```php
// config/filesystems.php
'mailbox' => [
    'driver' => 'local',
    'root' => env('MAILBOX_STORE_FILE_PATH', storage_path('app/mailbox')),
    'throw' => false,
],
```

You can also mix: messages on the volume, attachment bytes on S3 (use the disk from [A2](#a2-store-attachment-content-on-shared-storage)).

### B2. Volume requirements

- **Shared:** with more than one replica, or with separate worker containers, the volume must be mountable read-write by all of them at once. In Kubernetes that means a `ReadWriteMany` StorageClass such as AWS EFS, Azure Files, GCP Filestore, NFS, or CephFS. A regular `ReadWriteOnce` block volume (EBS, Persistent Disk, Azure Disk) only works with a single pod that runs both web and queue.
- **Writable by PHP:** the PHP process user (often `www-data`, UID 33) must be able to create files. In Kubernetes set `securityContext.fsGroup` to that group.

### B3. Example: Docker Compose

On a single Docker host, a named volume is already shared between services:

```yaml
x-app-env: &app-env
  APP_ENV: staging
  MAIL_MAILER: mailbox
  MAILBOX_STORE_DRIVER: file

services:
  app:
    image: my-app:latest
    environment: *app-env
    volumes:
      - mailbox:/var/www/html/storage/app/mailbox

  worker:
    image: my-app:latest
    environment: *app-env
    command: php artisan queue:work
    volumes:
      - mailbox:/var/www/html/storage/app/mailbox

volumes:
  mailbox:
```

For Docker Swarm across several nodes, back the volume with NFS (`driver_opts: { type: nfs, ... }`) so all nodes see the same files.

### B4. Example: Kubernetes

```yaml
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: app-mailbox
spec:
  accessModes: [ReadWriteMany]
  storageClassName: efs-sc          # any RWX-capable class
  resources:
    requests:
      storage: 5Gi
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app-web
spec:
  replicas: 3
  selector: { matchLabels: { app: app-web } }
  template:
    metadata: { labels: { app: app-web } }
    spec:
      securityContext:
        fsGroup: 33                  # www-data
      containers:
        - name: web
          image: registry.example.com/my-app:1.4.0
          env:
            - { name: MAIL_MAILER, value: mailbox }
            - { name: MAILBOX_STORE_DRIVER, value: file }
            - { name: MAILBOX_RETENTION_SCHEDULE, value: "true" }
          volumeMounts:
            - name: mailbox
              mountPath: /var/www/html/storage/app/mailbox
      volumes:
        - name: mailbox
          persistentVolumeClaim:
            claimName: app-mailbox
```

Give the queue worker Deployment the same `env`, `volumeMounts`, and `volumes`.

### B5. Trade-offs to keep in mind

- **Listing reads every message file.** Each dashboard page load and each poll (every 5 s by default) scans the directory. That is fine for hundreds of messages but slows down with thousands, and network filesystems make it worse. Keep retention short (see below) and consider raising `MAILBOX_POLLING_INTERVAL`.
- **No locking.** Concurrent captures are safe because each message is its own file. Two people marking the same message read at the same moment can overwrite each other, which is harmless for an inbox.

### Single replica only: SQLite on a persistent volume

If you run exactly **one** container that handles web and queue, you can keep the default `sqlite` driver and mount a `ReadWriteOnce` volume at `storage/app/mailbox`. Then run `php artisan mailbox:install` once against it. Don't share a SQLite file over NFS/EFS between several containers: SQLite's file locking is unreliable on network filesystems and the database can get corrupted. Use Option A or B instead.

---

## Retention

Shared storage keeps growing until something prunes it. Pick one:

```env
# Let the package register a daily `mailbox:clear --outdated` on Laravel's scheduler
MAILBOX_RETENTION_SCHEDULE=true
MAILBOX_RETENTION=86400   # keep 24 hours (the default)
```

This needs a scheduler running (`php artisan schedule:work` container, or a Kubernetes CronJob running `php artisan schedule:run` every minute). The task uses `onOneServer()`, so your cache store must be shared between containers (Redis, Memcached, database) for it to run only once.

Or run the purge directly as a Kubernetes CronJob:

```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: app-mailbox-purge
spec:
  schedule: "0 3 * * *"
  jobTemplate:
    spec:
      template:
        spec:
          restartPolicy: OnFailure
          containers:
            - name: purge
              image: registry.example.com/my-app:1.4.0
              command: ["php", "artisan", "mailbox:clear", "--outdated"]
              envFrom:
                - configMapRef: { name: app-mailbox }
                - secretRef: { name: app-secrets }
              # Option B: also mount the shared volume here
```

## Verifying the setup

From inside a running web container (`docker compose exec app …` or `kubectl exec deploy/app-web -- …`):

```bash
php artisan config:show mailbox.store
php artisan config:show mailbox.attachments
php artisan config:show filesystems.disks.mailbox

# Option A: make sure this is your real database, not a sqlite file
php artisan config:show "database.connections.$(printenv MAILBOX_STORE_DATABASE_CONNECTION)"
```

Then do an end-to-end check:

1. Send a message from inside a container, then open the dashboard and confirm it's there:
   ```bash
   php artisan tinker --execute 'Mail::raw("Hello from the cluster", fn ($m) => $m->to("qa@example.com")->subject("Mailbox check"));'
   ```
2. Restart the containers (`docker compose restart app` / `kubectl rollout restart deploy/app-web`).
3. Reload the dashboard a few times. The message should still be there no matter which replica answers.
4. Trigger a real email from a queued job and confirm it shows up too. That proves the workers write to the same storage.
