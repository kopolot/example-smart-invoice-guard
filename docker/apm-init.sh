#!/bin/sh
set -eu

ES="${ELASTICSEARCH_HOST:-http://elasticsearch:9200}"
ELASTIC_USER="${ELASTICSEARCH_USERNAME:-elastic}"
ELASTIC_PASS="${ELASTIC_PASSWORD:-changeme}"
KIBANA_SYSTEM_PASS="${KIBANA_SYSTEM_PASSWORD:-${ELASTIC_PASSWORD:-changeme}}"

auth_args() {
    printf '%s' "-u${ELASTIC_USER}:${ELASTIC_PASS}"
}

echo "Waiting for Elasticsearch security API..."
i=0
until curl -fsS "$(auth_args)" "${ES}/_security/_authenticate" >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "${i}" -ge 60 ]; then
        echo "Elasticsearch auth not ready after 60 attempts" >&2
        exit 1
    fi
    sleep 2
done

echo "Setting kibana_system password..."
curl -fsS -X POST "$(auth_args)" \
    -H 'Content-Type: application/json' \
    "${ES}/_security/user/kibana_system/_password" \
    -d "{\"password\":\"${KIBANA_SYSTEM_PASS}\"}" >/dev/null
echo "kibana_system password set"

# Indices normally created by Kibana's APM app / Fleet integration. Without them
# apm-server logs "refresh cache elasticsearch returned status 404" every 30s.
#
# .apm-source-map must match Kibana's apm-source-map index template (see
# create_apm_source_map_index_template in the APM plugin). A bare index without
# mappings makes Kibana's fleet sourcemap migration fail with:
#   No mapping found for [created] in order to sort on

ensure_index() {
    index="$1"
    body="$2"
    code="$(curl -s -o /dev/null -w '%{http_code}' "$(auth_args)" "${ES}/${index}")"
    if [ "${code}" = "404" ]; then
        curl -fsS -X PUT "$(auth_args)" "${ES}/${index}" \
            -H 'Content-Type: application/json' \
            -d "${body}"
        echo "created ${index}"
    else
        echo "skip ${index} (http ${code})"
    fi
}

ensure_index ".apm-agent-configuration" '{"settings":{"index.number_of_replicas":0}}'
ensure_index ".apm-custom-link" '{"settings":{"index.number_of_replicas":0}}'
ensure_index ".apm-source-map" '{
  "settings": {
    "index": {
      "number_of_shards": 1,
      "number_of_replicas": 0,
      "auto_expand_replicas": "0-1",
      "hidden": true
    }
  },
  "mappings": {
    "dynamic": "strict",
    "properties": {
      "fleet_id": { "type": "keyword" },
      "created": { "type": "date" },
      "content": { "type": "binary" },
      "content_sha256": { "type": "keyword" },
      "file": {
        "properties": {
          "path": { "type": "keyword" }
        }
      },
      "service": {
        "properties": {
          "name": { "type": "keyword" },
          "version": { "type": "keyword" }
        }
      }
    }
  }
}'
