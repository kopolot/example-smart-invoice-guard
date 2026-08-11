#!/bin/sh
set -eu

# Indices normally created by Kibana's APM app / Fleet integration. Without them
# apm-server logs "refresh cache elasticsearch returned status 404" every 30s.
for index in .apm-agent-configuration .apm-custom-link .apm-source-map; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "http://elasticsearch:9200/${index}")"
    if [ "${code}" = "404" ]; then
        curl -fsS -X PUT "http://elasticsearch:9200/${index}" \
            -H 'Content-Type: application/json' \
            -d '{"settings":{"index.number_of_replicas":0}}'
        echo "created ${index}"
    else
        echo "skip ${index} (http ${code})"
    fi
done
