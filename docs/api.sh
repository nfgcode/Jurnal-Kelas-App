#!/usr/bin/env bash
# Tiny curl helper for browsing the Jurnal Kelas API from a terminal.
#
#   source docs/api.sh
#   apilogin admin@jurnalkelas.app password admin
#   apic /dashboard
#   apic '/jurnal?q=basis+data&sort=tanggal'
#
# Override the base URL with:  export API_BASE=http://localhost:8888/api
API_BASE="${API_BASE:-http://localhost:8888/api}"

# apilogin <user> <password> <role>  -> stores a bearer token in $API_TOKEN
apilogin() {
  API_TOKEN=$(curl -s -X POST "$API_BASE/login" -H 'Accept: application/json' \
    -d "user=$1" -d "password=$2" -d "role=${3:-}" \
    | sed -nE 's/.*"token":"([^"]+)".*/\1/p')
  if [ -n "$API_TOKEN" ]; then
    echo "Logged in — token stored in \$API_TOKEN."
  else
    echo "Login failed (check credentials / role)."
    return 1
  fi
}

# apic <path>  -> GET an endpoint with the stored token (e.g. apic /dashboard)
apic() {
  curl -s -H 'Accept: application/json' -H "Authorization: Bearer $API_TOKEN" "$API_BASE$1"
}
