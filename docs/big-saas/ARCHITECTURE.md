# Big SaaS — architektura wieloklastrowa

Branch `big-saas` rozszerza Smart Invoice Guard o model **multi-tenant SaaS na dużą skalę**: wiele klastrów Percona XtraDB Cluster (PXC), osobne bazy danych per tenant, wiele instancji PHP-FPM, routing warstwy edge (Nginx) oraz zdefiniowane profile zasobów dla planów **normal**, **premium** i **enterprise**.

> Lokalnie: Docker Compose overlay (`compose.big-saas.yaml`).  
> Produkcja: ten sam podział mapuje się na K8s / VM fleet + IaC (Terraform/Ansible).

---

## Diagram wysokiego poziomu

```
                         ┌─────────────────────────────────────────┐
                         │           Nginx LB (nginx-lb)            │
                         │  map: X-Tenant-Tier + X-Premium-Cluster  │
                         └───────────┬─────────────────────────────┘
                                     │
         ┌───────────────────────────┼───────────────────────────┐
         │                           │                           │
         ▼                           ▼                           ▼
  php-shared-1/2              php-premium-1..3           php-enterprise-1a/b/c
  (normal tier)               (premium shard)            (dedykowany pool)
         │                           │                           │
         ▼                           ▼                           ▼
   shared-db                  premium-db-1..3              enterprise-db-1
   (ProxySQL)                  (ProxySQL ×3)                (ProxySQL)
         │                           │                           │
         ▼                           ▼                           ▼
  PXC shared (×3)            PXC premium 1..3 (×3*)      PXC enterprise-1 (×3*)
  sig-shared                 sig-premium-{N}               sig-enterprise-{N}

  * produkcja: 3 węzły Galera; lokalnie: 1 węzeł = stand-in dev

         ┌─────────────────────────────────────────┐
         │  control-db → PXC control (tenant_registry) │
         │  tenant_id, tier, cluster_id, db_name, …    │
         └─────────────────────────────────────────┘
```

---

## Plany i izolacja danych

| Plan | Klaster PXC | Baza danych | PHP-FPM | Limity (przykład) |
| --- | --- | --- | --- | --- |
| **normal** | `sig-shared` (wspólny) | `sig_shared_tenant_{id}` — **osobna DB per tenant** | `php-shared-1/2`, pool 24 workers | 500 faktur, 60 API rpm |
| **premium** | `sig-premium-1..3` (shard) | `sig_premium_tenant_{id}` | `php-premium-{N}`, pool 48 workers | 50 000 faktur, 300 API rpm |
| **enterprise** | `sig-enterprise-{N}` (dedykowany) | `sig_enterprise_{id}` | `php-enterprise-{N}` ×3 repliki, pool 96 | bez limitu faktur, 1000 API rpm |

### Sharding premium

Tenant premium trafia do klastra `1..N` deterministycznie:

```php
cluster_id = ((tenant_id - 1) % N) + 1   // N = 3 domyślnie
```

Implementacja: `App\Services\BigSaas\TenantContextResolver`.

---

## Profile zasobów

Pełna definicja CPU/RAM/disk, PHP-FPM `pm.*`, ProxySQL `max_connections` i limitów biznesowych:

**`docker/big-saas/tiers.yaml`**

Skopiuj wartości do IaC (K8s `resources`, HPA, node pools) lub użyj jako checklistę capacity planningu.

---

## Routing HTTP (Nginx)

Nagłówki (w produkcji ustawia je edge po lookup w control plane):

| Nagłówek | Wartości | Efekt |
| --- | --- | --- |
| `X-Tenant-Tier` | `normal` \| `premium` \| `enterprise` | wybór puli PHP-FPM |
| `X-Premium-Cluster` | `1` \| `2` \| `3` | shard premium (gdy tier=premium) |

Konfiguracja: `docker/big-saas/nginx/upstreams.conf`, `docker/big-saas/nginx/default.conf`.

---

## Uruchomienie lokalne

### Pełny stack Big SaaS

```bash
docker compose -f compose.yaml -f compose.big-saas.yaml --profile big-saas up -d --build
```

Uruchamia:
- **App layer**: nginx-lb, 2× shared PHP, 3× premium PHP, 3× enterprise PHP
- **Data layer**: istniejący PXC shared + control + premium 1–3 + enterprise 1
- Profile `legacy-single` wyłącza stary single `php` / `nginx`

### Tylko warstwa aplikacji (bez dodatkowych klastrów DB)

```bash
docker compose -f compose.yaml -f compose.big-saas.yaml \
  --profile big-saas-app up -d
```

Wymaga `--profile legacy-single` jeśli chcesz równolegle stary nginx — **nie rób tego**; używaj wyłącznie nginx-lb.

### Zmienne aplikacji

```bash
cat .env.big-saas.example >> .env   # lub merge ręcznie
```

Kluczowe: `BIG_SAAS_ENABLED=true`, hosty `*_DB_HOST` z aliasów Docker.

---

## Konfiguracja Laravel

| Plik | Rola |
| --- | --- |
| `config/big-saas.php` | plany, sharding, dev override |
| `config/database.big-saas.php` | połączenia `control`, `shared`, `premium_1..3`, `enterprise_1` |
| `app/Services/BigSaas/TenantContextResolver.php` | resolve connection + DB name + upstream |

Control plane (docelowo): tabela `tenants` w `tenant_registry` z polami m.in. `tier`, `cluster_id`, `database_name`, `php_upstream`, limity z `tiers.yaml`.

---

## Skalowanie produkcyjne (checklist)

1. **PXC**: każdy klaster premium/enterprise = min. 3 węzły + ProxySQL (writer/reader hostgroups).
2. **PHP-FPM**: HPA po `pm.status` / latency; osobne deploymenty per tier.
3. **Nginx / Ingress**: ten sam model map co `upstreams.conf`; opcjonalnie osobny ingress per enterprise.
4. **Redis**: cluster mode dla sesji/pulse; enterprise opcjonalnie dedykowany.
5. **Elasticsearch**: shared index (normal), dedykowany index/cluster (premium+).
6. **RabbitMQ**: federacja / osobne vhosty (`shared`, `premium`, `enterprise`).
7. **Provisioning tenant DB**: job `CreateTenantDatabase` na właściwym ProxySQL + `migrate --database=…`.

---

## Kolejne kroki (poza tym branchem)

- Migracja `tenant_registry` + API provisioningu
- Middleware `ResolveTenantContext` (JWT / subdomain → tier)
- Dynamiczne `Config::set('database.default', …)` per request
- Helm chart / Terraform modules per `tiers.yaml`
